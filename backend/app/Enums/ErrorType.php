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
}
