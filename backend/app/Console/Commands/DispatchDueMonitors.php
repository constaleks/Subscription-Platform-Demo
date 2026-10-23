<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Monitor;
use App\Jobs\CheckMonitorJob;
use App\Enums\Plan;

#[Signature('app:dispatch-due-monitors')]
#[Description('Dispatch a check job for every active monitor that is due')]
class DispatchDueMonitors extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $tick = $now->copy()->startOfMinute();
        $dispatched = 0;
 
        Monitor::query()
            ->where('is_active', true)
            ->where('next_check_at', '<=', $now)
            ->with('user.subscriptions') // avoids one query per monitor when resolving the plan
            ->chunkById(200, function ($monitors) use ($now, $tick, &$dispatched) {
                foreach ($monitors as $monitor) {
                    // Atomic claim: only the process whose UPDATE matches a row may dispatch.
                    // This prevents double dispatch even if two schedulers overlap.
                    //
                    // The next check time is computed from the start of the current minute,
                    // not from now(). Otherwise a 5-minute monitor claimed at 12:00:01 would be
                    // due at 12:05:01, miss the 12:05 tick and effectively run every 6 minutes.
                    $claimed = Monitor::whereKey($monitor->id)
                        ->where('is_active', true)
                        ->where('next_check_at', '<=', $now)
                        ->update(['next_check_at' => $tick->copy()->addSeconds($monitor->interval_seconds)]);
 
                    if ($claimed === 0) {
                        continue;
                    }
 
                    CheckMonitorJob::dispatch($monitor)->onQueue($this->queueFor($monitor));
                    $dispatched++;
                }
            });
 
        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} monitor check(s).");
        }
 
        return self::SUCCESS;
    }
 
    /**
     * Pro checks go to the high-priority queue so free-tier volume can't starve them. 
     */
    private function queueFor(Monitor $monitor): string
    {
        return $monitor->user->plan() === Plan::Pro ? 'checks-high' : 'checks-low';
    }
}
