<?php

namespace App\Support;

use App\Enums\ErrorType;

/** Outcome of one check. Not persisted itself; the job stores it in monitor_checks. */
final readonly class CheckResult
{
    public function __construct(
        public bool $isUp,
        public ?int $httpStatusCode = null,
        public ?int $responseTimeMs = null,
        public ?ErrorType $errorType = null,
        public ?string $errorMessage = null,
    ) {}

    public static function up(int $httpStatusCode, int $responseTimeMs): self
    {
        return new self(true, $httpStatusCode, $responseTimeMs);
    }

    public static function down(
        ErrorType $errorType,
        string $message,
        ?int $httpStatusCode = null,
        ?int $responseTimeMs = null,
    ): self {
        // error_message column is varchar(255)
        return new self(false, $httpStatusCode, $responseTimeMs, $errorType, mb_substr($message, 0, 255));
    }
}