<?php

namespace App\Enums;

enum ErrorType: string
{
    case Timeout = 'timeout';
    case Dns = 'dns';
    case Connection = 'connection';
    case Tls = 'tls';
    case UnexpectedStatus = 'unexpected_status';
    case Blocked = 'blocked';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Timeout => 'Timeout',
            self::Dns => 'DNS error',
            self::Connection => 'Connection error',
            self::Tls => 'TLS/SSL error',
            self::UnexpectedStatus => 'Unexpected status code',
            self::Blocked => 'Blocked URL',
            self::Other => 'Other error',
        };
    }
}
