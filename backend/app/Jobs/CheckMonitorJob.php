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
use App\Services\IncidentManager;
use App\Notifications\IncidentOpened;
use App\Notifications\IncidentResolved;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

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
    public function handle(MonitorChecker $checker, IncidentManager $incidents): void
    {
        // The model is reloaded from the DB when the job runs. It may have been paused meanwhile.
        if (! $this->monitor->is_active) {
            return;
        }
 
        $checkedAt = now();
        $result = $checker->check($this->monitor);

        $this->recordResult($result, $checkedAt, $incidents);
    }

    private function recordResult(CheckResult $result, Carbon $checkedAt, IncidentManager $incidents): void
    {
        // Check row, state change and incident row are saved together or not at all
        $notification = DB::transaction(function () use ($result, $checkedAt, $incidents): ?Notification {
            // The HTTP request may have taken up to a minute, so the copy loaded at job start can be stale.
            // The row lock serializes with pause()/resume() (their UPDATE takes the same lock),
            // so the state below is always computed from the latest row.
            $monitor = Monitor::lockForUpdate()->find($this->monitor->id);

            // Paused (or deleted) while the request was in flight: discard the result
            if (! $monitor?->is_active) {
                return null;
            }

            $check = MonitorCheck::create([
                'monitor_id' => $monitor->id,
                'is_up' => $result->isUp,
                'http_status_code' => $result->httpStatusCode,
                'response_time_ms' => $result->responseTimeMs,
                'error_type' => $result->errorType,
                'error_message' => $result->errorMessage,
                'checked_at' => $checkedAt,
            ]);

            $previous = $monitor->status;

            $monitor->last_checked_at = $check->checked_at;

            if ($check->is_up) {
                $monitor->consecutive_failures = 0;
                $monitor->status = MonitorStatus::Up;
            } else {
                $monitor->consecutive_failures++;

                // One failed check may be a blip: only flip to "down" after N in a row
                if ($monitor->consecutive_failures >= config('monitoring.failure_threshold')) {
                    $monitor->status = MonitorStatus::Down;
                }
            }

            $monitor->save();

            if ($previous !== MonitorStatus::Down && $monitor->status === MonitorStatus::Down) {
                return new IncidentOpened($incidents->open($monitor));
            }
 
            if ($previous === MonitorStatus::Down && $monitor->status === MonitorStatus::Up) {
                $incident = $incidents->resolve($monitor, $check->checked_at);
 
                return $incident ? new IncidentResolved($incident) : null;
            }
 
            return null;
        });
 
        // Outside the transaction, so the queued notification can always find the committed incident row
        if ($notification) {
            $this->monitor->user->notify($notification);
        }
    }
}
