<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\Incident;
use Illuminate\Support\Carbon;

/**
 * Opens and closes incidents. Contains no notification logic:
 * the caller decides whether to notify.
 */
class IncidentManager
{
    /**
     * Opens an incident for a monitor that has just been declared down.
     * Idempotent: if one is already open, that one is returned.
     */
    public function open(Monitor $monitor): Incident
    {
        if ($existing = $monitor->openIncident()->first()) {
            return $existing;
        }

        // The last N checks are the failed streak that led to "down" (N = consecutive_failures).
        // The oldest of them is when the outage really began, not the check that crossed the threshold.
        $firstFailure = $monitor->checks()
            ->where('is_up', false)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit(max(1, $monitor->consecutive_failures))
            ->get()
            ->last();

        return Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => $firstFailure?->checked_at ?? now(),
            'error_type' => $firstFailure?->error_type,
            'error_message' => $firstFailure?->error_message,
        ]);
    }

    /** Closes the monitor's open incident, if there is one. */
    public function resolve(Monitor $monitor, Carbon $at): ?Incident
    {
        $incident = $monitor->openIncident()->first();

        $incident?->update(['resolved_at' => $at]);

        return $incident;
    }
}