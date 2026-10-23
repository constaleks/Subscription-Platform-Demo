<?php

namespace App\Support;

use App\Exceptions\UnsafeUrlException;

/**
 * SSRF guard shared by the validation rule (save time) and the checker (request time).
 */
final class HostGuard
{
    public const UNRESOLVABLE = 'host could not be resolved.';

    /**
     * Non-public IPv4 ranges (IANA special-purpose registry). Covers what
     * FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE misses, e.g. CGNAT.
     */
    private const BLOCKED_V4 = [
        '0.0.0.0/8',          // "this network"
        '10.0.0.0/8',         // private
        '100.64.0.0/10',      // carrier-grade NAT (also Alibaba Cloud metadata)
        '127.0.0.0/8',        // loopback
        '169.254.0.0/16',     // link-local (cloud metadata)
        '172.16.0.0/12',      // private
        '192.0.0.0/24',       // IETF protocol assignments
        '192.0.2.0/24',       // documentation
        '192.88.99.0/24',     // 6to4 relay anycast
        '192.168.0.0/16',     // private
        '198.18.0.0/15',      // benchmarking
        '198.51.100.0/24',    // documentation
        '203.0.113.0/24',     // documentation
        '224.0.0.0/4',        // multicast
        '240.0.0.0/4',        // reserved, incl. broadcast
    ];

    /**
     * IPv6 is allowed only inside global unicast (2000::/3), which already
     * excludes loopback, IPv4-mapped (::ffff:0:0/96), NAT64 (64:ff9b::/96),
     * unique-local, link-local and multicast. These are the special ranges
     * inside 2000::/3; 6to4 and Teredo embed an IPv4 address that could be private.
     */
    private const GLOBAL_UNICAST_V6 = '2000::/3';

    private const BLOCKED_V6 = [
        '2001::/23',          // IETF protocol assignments, incl. Teredo (2001::/32)
        '2001:db8::/32',      // documentation
        '2002::/16',          // 6to4
        '3fff::/20',          // documentation
    ];

    /**
     * Returns why requesting this URL is unsafe, or null when it is fine.
     */
    public static function unsafeReason(string $url): ?string
    {
        try {
            self::resolvePublic($url);

            return null;
        } catch (UnsafeUrlException $e) {
            return $e->reason;
        }
    }

    /**
     * Resolves the URL's host and returns its addresses, all of them public.
     * The caller must connect to exactly these addresses (see MonitorChecker),
     * otherwise a second DNS lookup could return a different, private one.
     *
     * @return array{host: string, port: int, ips: string[], isIpLiteral: bool}
     *
     * @throws UnsafeUrlException
     */
    public static function resolvePublic(string $url, string $subject = 'URL'): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('must use http or https.', $subject);
        }

        if (! $host) {
            throw new UnsafeUrlException('must contain a valid host.', $subject);
        }

        $host = strtolower(trim($host, '[]')); // IPv6 literals come wrapped in brackets
        $isIpLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $ips = $isIpLiteral ? [$host] : self::resolve($host);

        if ($ips === []) {
            throw new UnsafeUrlException(self::UNRESOLVABLE, $subject);
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new UnsafeUrlException('must point to a public address.', $subject);
            }
        }

        return [
            'host' => $host,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'ips' => $ips,
            'isIpLiteral' => $isIpLiteral,
        ];
    }

    public static function isPublic(string $ip): bool
    {
        $passesPhpFilter = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        $binary = @inet_pton($ip);

        if (! $passesPhpFilter || $binary === false) {
            return false;
        }

        if (strlen($binary) === 4) {
            return ! self::inAnyRange($binary, self::BLOCKED_V4);
        }

        return self::inRange($binary, self::GLOBAL_UNICAST_V6)
            && ! self::inAnyRange($binary, self::BLOCKED_V6);
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        ))));
    }

    /** @param string[] $cidrs */
    private static function inAnyRange(string $binary, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (self::inRange($binary, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function inRange(string $binary, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $subnet = inet_pton($subnet);
        $bits = (int) $bits;

        if (strlen($subnet) !== strlen($binary)) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);

        if (substr($binary, 0, $fullBytes) !== substr($subnet, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($binary[$fullBytes]) & $mask) === (ord($subnet[$fullBytes]) & $mask);
    }
}
