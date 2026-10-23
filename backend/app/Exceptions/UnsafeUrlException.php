<?php

namespace App\Exceptions;

use RuntimeException;

class UnsafeUrlException extends RuntimeException
{
    /**
     * @param  string  $reason  Written to follow a subject, e.g. "must point to a public address."
     * @param  string  $subject  What was checked, e.g. "URL" or "Redirect target"
     */
    public function __construct(public readonly string $reason, public readonly string $subject = 'URL')
    {
        parent::__construct("{$subject} {$reason}");
    }
}
