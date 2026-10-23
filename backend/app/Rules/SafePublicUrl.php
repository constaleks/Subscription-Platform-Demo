<?php

namespace App\Rules;

use App\Support\HostGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SSRF guard (save-time). MonitorChecker re-checks at request time,
 * because DNS can change after validation.
 */
class SafePublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($reason = HostGuard::unsafeReason((string) $value)) {
            $fail("The :attribute {$reason}");
        }
    }
}