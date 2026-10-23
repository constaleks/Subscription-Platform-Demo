<?php

namespace App\Services;

use App\Enums\ErrorType;
use App\Exceptions\TooManyRedirectsException;
use App\Exceptions\UnsafeUrlException;
use App\Models\Monitor;
use App\Support\CheckResult;
use App\Support\HostGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Performs one HTTP check and returns the result. Knows nothing about the
 * queue or the database, so it can be tested with Http::fake().
 */
class MonitorChecker
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function check(Monitor $monitor): CheckResult
    {
        $startedAt = microtime(true);

        try {
            $response = $this->send($monitor, $startedAt);
        } catch (UnsafeUrlException $e) {
            return $this->blocked($e);
        } catch (TooManyRedirectsException $e) {
            return CheckResult::down(ErrorType::Other, $e->getMessage());
        } catch (ConnectionException $e) {
            return CheckResult::down($this->classify($e->getMessage()), $e->getMessage());
        } catch (Throwable $e) {
            return CheckResult::down(ErrorType::Other, $e->getMessage());
        }

        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->status();
        $expected = $monitor->expected_status_code;

        // null means "any 2xx" (after following redirects)
        $isUp = $expected === null
            ? $status >= 200 && $status < 300
            : $status === (int) $expected;

        if ($isUp) {
            return CheckResult::up($status, $elapsedMs);
        }

        return CheckResult::down(
            ErrorType::UnexpectedStatus,
            'Expected '.($expected ?? '2xx').", got {$status}",
            $status,
            $elapsedMs,
        );
    }

    /**
     * Sends the request and follows redirects by hand, so that every hop is
     * validated and pinned to the address that was validated (no DNS rebinding),
     * and the user's custom headers never leave the monitor's own origin.
     */
    private function send(Monitor $monitor, float $startedAt): Response
    {
        $url = $monitor->url;
        $origin = $this->origin(new Uri($url));
        $deadline = $startedAt + $monitor->timeout_seconds;
        $maxRedirects = (int) config('monitoring.max_redirects');

        for ($redirects = 0; ; $redirects++) {
            // Re-validated at request time on every hop: DNS may have changed since the URL was saved
            $target = HostGuard::resolvePublic($url, $redirects === 0 ? 'URL' : 'Redirect target');
            $uri = new Uri($url);

            $response = $this->sendOnce(
                $monitor,
                $url,
                $target,
                $this->origin($uri) === $origin ? ($monitor->request_headers ?? []) : [],
                $deadline,
            );

            $location = $response->header('Location');

            if (! in_array($response->status(), self::REDIRECT_STATUSES, true) || $location === '') {
                return $response;
            }

            if ($redirects >= $maxRedirects) {
                throw new TooManyRedirectsException("Too many redirects (more than {$maxRedirects})");
            }

            $url = (string) UriResolver::resolve($uri, new Uri($location));
        }
    }

    /** @param  array{host: string, port: int, ips: string[], isIpLiteral: bool}  $target */
    private function sendOnce(Monitor $monitor, string $url, array $target, array $headers, float $deadline): Response
    {
        if (! function_exists('curl_init')) {
            // Guzzle's stream handler can't pin the resolved address
            throw new RuntimeException('ext-curl is required for monitor checks.');
        }

        $remaining = $deadline - microtime(true);

        if ($remaining <= 0) {
            throw new ConnectionException('Operation timed out (redirects took longer than the monitor timeout)');
        }

        // We only need the status line and headers. Guzzle's 'stream' option would switch to the
        // stream handler, which can't pin the address, so instead the transfer is aborted once
        // the headers have arrived and the body is never downloaded.
        $headersOnly = null;

        $options = [
            'allow_redirects' => false, // followed in send()
            'on_headers' => function (ResponseInterface $response) use (&$headersOnly) {
                // 1xx interim responses (e.g. 103 Early Hints) are followed by the real one
                if ($response->getStatusCode() >= 200) {
                    $headersOnly = $response;

                    throw new RuntimeException('Headers received; body not needed');
                }
            },
            'proxy' => '', // never route through HTTP(S)_PROXY from the environment; the pinned address would not apply
        ];

        if (! $target['isIpLiteral']) {
            $addresses = array_map(fn (string $ip) => str_contains($ip, ':') ? "[{$ip}]" : $ip, $target['ips']);
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:".implode(',', $addresses)]];
        }

        try {
            return Http::timeout($remaining)
                ->connectTimeout(min(10, $remaining))
                ->withUserAgent(config('monitoring.user_agent'))
                ->withHeaders($headers)
                ->withOptions($options)
                ->send($monitor->method->value, $url);
        } catch (Throwable $e) {
            if ($headersOnly !== null) {
                return new Response($headersOnly);
            }

            throw $e;
        }
    }

    private function origin(Uri $uri): string
    {
        $scheme = strtolower($uri->getScheme());
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);

        return "{$scheme}://".strtolower($uri->getHost()).":{$port}";
    }

    private function blocked(UnsafeUrlException $e): CheckResult
    {
        // A host that no longer resolves is a DNS failure (site down), not a blocked request
        $type = $e->reason === HostGuard::UNRESOLVABLE ? ErrorType::Dns : ErrorType::Blocked;

        return CheckResult::down($type, $e->getMessage());
    }

    private function classify(string $message): ErrorType
    {
        if (preg_match('/cURL error (\d+)/', $message, $matches)) {
            return match (true) {
                (int) $matches[1] === 28 => ErrorType::Timeout,
                (int) $matches[1] === 6 => ErrorType::Dns,
                in_array((int) $matches[1], [35, 51, 58, 60, 77, 83, 90, 91], true) => ErrorType::Tls,
                default => ErrorType::Connection,
            };
        }

        return str_contains(strtolower($message), 'timed out')
            ? ErrorType::Timeout
            : ErrorType::Connection;
    }
}
