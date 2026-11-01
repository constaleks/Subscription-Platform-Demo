<?php

namespace Tests\Feature\Monitors;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** checks, incidents and stats endpoints. The user is on the free plan (3 days retention). */
class MonitorHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Monitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfSecond());

        $this->user = User::factory()->create();
        $this->monitor = Monitor::factory()->for($this->user)->create();
        $this->actingAs($this->user);
    }

    private function check(bool $isUp, string $ago, ?int $responseTimeMs = 100): MonitorCheck
    {
        return MonitorCheck::create([
            'monitor_id' => $this->monitor->id,
            'is_up' => $isUp,
            'http_status_code' => $isUp ? 200 : null,
            'response_time_ms' => $responseTimeMs,
            'checked_at' => now()->sub($ago),
        ]);
    }

    public function test_other_users_monitor_is_not_found(): void
    {
        $foreign = Monitor::factory()->create();

        foreach (['checks', 'incidents', 'stats'] as $endpoint) {
            $this->getJson("/api/v1/monitors/{$foreign->id}/{$endpoint}")->assertNotFound();
        }
    }

    public function test_checks_are_listed_newest_first_within_retention(): void
    {
        $old = $this->check(true, '2 hours');
        $new = $this->check(false, '1 hour', null);
        $this->check(true, '4 days'); // older than the free plan's 3 days

        $this->getJson("/api/v1/monitors/{$this->monitor->id}/checks")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id);
    }

    public function test_incidents_are_listed_with_duration(): void
    {
        Incident::create([
            'monitor_id' => $this->monitor->id,
            'started_at' => now()->subMinutes(30),
            'resolved_at' => now()->subMinutes(20),
        ]);

        $this->getJson("/api/v1/monitors/{$this->monitor->id}/incidents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.duration_seconds', 600)
            ->assertJsonPath('data.0.is_ongoing', false);
    }

    public function test_stats_windows_follow_plan_retention(): void
    {
        $this->getJson("/api/v1/monitors/{$this->monitor->id}/stats")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.days', 1)
            ->assertJsonPath('data.1.days', 3);
    }

    public function test_stats_without_checks_report_no_data(): void
    {
        $this->getJson("/api/v1/monitors/{$this->monitor->id}/stats")
            ->assertJsonPath('data.0.checks_total', 0)
            ->assertJsonPath('data.0.uptime_percent', null)
            ->assertJsonPath('data.0.avg_response_ms', null)
            ->assertJsonPath('data.0.downtime_seconds', 0);
    }

    public function test_stats_compute_uptime_and_response_time_per_window(): void
    {
        $this->check(true, '1 hour', 100);
        $this->check(true, '2 hours', 200);
        $this->check(false, '3 hours', null); // timeout: no response time
        $this->check(true, '2 days', 600);   // only in the 3-day window

        $response = $this->getJson("/api/v1/monitors/{$this->monitor->id}/stats")->assertOk();

        $response
            ->assertJsonPath('data.0.checks_total', 3)
            ->assertJsonPath('data.0.checks_up', 2)
            ->assertJsonPath('data.0.uptime_percent', 66.66) // floored, not rounded to 66.67
            ->assertJsonPath('data.0.avg_response_ms', 150);

        $response
            ->assertJsonPath('data.1.checks_total', 4)
            ->assertJsonPath('data.1.uptime_percent', 75)
            ->assertJsonPath('data.1.avg_response_ms', 300);
    }

    public function test_stats_downtime_is_clipped_to_the_window(): void
    {
        // Began before the 24h window: only the part inside it counts there
        Incident::create([
            'monitor_id' => $this->monitor->id,
            'started_at' => now()->subHours(25),
            'resolved_at' => now()->subHours(23),
        ]);

        // Still open: counts until now
        Incident::create([
            'monitor_id' => $this->monitor->id,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->getJson("/api/v1/monitors/{$this->monitor->id}/stats")
            ->assertJsonPath('data.0.incidents', 2)
            ->assertJsonPath('data.0.downtime_seconds', 3600 + 600)
            ->assertJsonPath('data.1.incidents', 2)
            ->assertJsonPath('data.1.downtime_seconds', 7200 + 600);
    }
}
