<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Monitor;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\MonitorChecker;
use App\Models\MonitorCheck;
use App\Support\CheckResult;
use Illuminate\Support\Carbon;
use App\Enums\MonitorStatus;

class CheckMonitorJob implements ShouldQueue
{
    use Queueable;

    // A failed check is a normal result, not a job failure, so never retry the job itself
    public int $tries = 1;
 
    // Must exceed the largest monitor timeout (60s) plus overhead
    public int $timeout = 90;
 
    // Monitor deleted while the job was waiting: just drop the job
    public bool $deleteWhenMissingModels = true;
 
    /**
     * Create a new job instance.
     */
    public function __construct(public Monitor $monitor) {}
 
    /**
     * Never run two checks for the same monitor at the same time.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("check-monitor:{$this->monitor->id}"))
                ->dontRelease()
                ->expireAfter(120),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(MonitorChecker $checker): void
    {
        // The model is reloaded from the DB when the job runs. It may have been paused meanwhile.
        if (! $this->monitor->is_active) {
            return;
        }
 
        $checkedAt = now();
        $result = $checker->check($this->monitor);
 
        MonitorCheck::create([
            'monitor_id' => $this->monitor->id,
            'is_up' => $result->isUp,
            'http_status_code' => $result->httpStatusCode,
            'response_time_ms' => $result->responseTimeMs,
            'error_type' => $result->errorType,
            'error_message' => $result->errorMessage,
            'checked_at' => $checkedAt,
        ]);
 
        $this->updateMonitor($result, $checkedAt);
    }

    private function updateMonitor(CheckResult $result, Carbon $checkedAt): void
    {
        $monitor = $this->monitor;
        $monitor->last_checked_at = $checkedAt;
 
        if ($result->isUp) {
            $monitor->consecutive_failures = 0;
            $monitor->status = MonitorStatus::Up;
        } else {
            $monitor->consecutive_failures++;
 
            // One failed check may be a blip: only flip to "down" after N in a row
            if ($monitor->consecutive_failures >= config('monitoring.failure_threshold')) {
                $monitor->status = MonitorStatus::Down;
            }
        }
 
        $monitor->save(); // only dirty columns are written, so a concurrent pause isn't overwritten
    }
}
