<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Uptime, response time and downtime of a monitor over the last N days.
 * Read-only: computed from monitor_checks and incidents on every call.
 */
class MonitorStats
{
    /** Windows offered to every plan, cut down to the plan's retention */
    private const WINDOW_DAYS = [1, 7, 30, 90];

    /**
     * One entry per window, shortest first. Windows longer than the retention are
     * dropped (checks older than that are not shown), and the retention itself is
     * always included, so a 3-day free plan gets [1, 3].
     *
     * @return list<array{days: int, from: string, checks_total: int, checks_up: int, uptime_percent: ?float, avg_response_ms: ?int, incidents: int, downtime_seconds: int}>
     */
    public function forMonitor(Monitor $monitor, int $retentionDays): array
    {
        $now = now();

        $windows = collect(self::WINDOW_DAYS)
            ->filter(fn (int $days) => $days < $retentionDays)
            ->push($retentionDays)
            ->values();

        // One query for all windows: every incident that overlaps the longest one
        $incidents = $this->incidentsSince($monitor, $now->copy()->subDays($windows->max()));

        return $windows
            ->map(function (int $days) use ($monitor, $now, $incidents) {
                $from = $now->copy()->subDays($days);

                return [
                    'days' => $days,
                    'from' => $from->toIso8601String(),
                    ...$this->checkStats($monitor, $from),
                    ...$this->downtimeStats($incidents, $from, $now),
                ];
            })
            ->all();
    }

    /** @return array{checks_total: int, checks_up: int, uptime_percent: ?float, avg_response_ms: ?int} */
    private function checkStats(Monitor $monitor, Carbon $from): array
    {
        // Plain SQL aggregates so it works on both PostgreSQL and SQLite (tests).
        // Uses the (monitor_id, checked_at) index.
        $row = $monitor->checks()
            ->where('checked_at', '>=', $from)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_up THEN 1 ELSE 0 END) AS up')
            ->selectRaw('AVG(response_time_ms) AS avg_response_ms')
            ->toBase()
            ->first();

        $total = (int) $row->total;
        $up = (int) $row->up;

        return [
            'checks_total' => $total,
            'checks_up' => $up,
            // null instead of 0 or 100 when nothing was checked yet: "no data" is not "down"
            // Floored, not rounded, so a single failure never shows as 100%
            'uptime_percent' => $total > 0 ? floor($up / $total * 10000) / 100 : null,
            // AVG ignores checks without a response time (timeouts, DNS errors...)
            'avg_response_ms' => $row->avg_response_ms !== null ? (int) round($row->avg_response_ms) : null,
        ];
    }

    /** @return Collection<int, Incident> */
    private function incidentsSince(Monitor $monitor, Carbon $from): Collection
    {
        return $monitor->incidents()
            ->where(fn ($query) => $query->whereNull('resolved_at')->orWhere('resolved_at', '>', $from))
            ->get(['id', 'started_at', 'resolved_at']);
    }

    /**
     * Incidents are clipped to the window: an outage that began before it
     * only counts from the window start, an open one until now.
     *
     * @param  Collection<int, Incident>  $incidents
     * @return array{incidents: int, downtime_seconds: int}
     */
    private function downtimeStats(Collection $incidents, Carbon $from, Carbon $now): array
    {
        $inWindow = $incidents->filter(fn (Incident $incident) => ($incident->resolved_at ?? $now)->gt($from));

        $seconds = $inWindow->sum(function (Incident $incident) use ($from, $now) {
            $start = max($incident->started_at->getTimestamp(), $from->getTimestamp());
            $end = ($incident->resolved_at ?? $now)->getTimestamp();

            return max(0, $end - $start);
        });

        return [
            'incidents' => $inWindow->count(),
            'downtime_seconds' => (int) $seconds,
        ];
    }
}
