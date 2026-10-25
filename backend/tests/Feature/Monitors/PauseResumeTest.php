<?php

namespace Tests\Feature\Monitors;

use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PauseResumeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_user_can_pause_monitor(): void
    {
        $monitor = Monitor::factory()->for($this->user)->create();

        $this->postJson("/api/v1/monitors/{$monitor->id}/pause")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.paused_reason', 'user');
    }

    public function test_pausing_closes_open_incident(): void
    {
        $monitor = Monitor::factory()->for($this->user)->down()->create();
        $incident = Incident::create(['monitor_id' => $monitor->id, 'started_at' => now()->subMinutes(10)]);

        $this->postJson("/api/v1/monitors/{$monitor->id}/pause")->assertOk();

        $this->assertNotNull($incident->fresh()->resolved_at);
        $this->assertNull($monitor->openIncident()->first());
    }

    public function test_user_can_resume_monitor(): void
    {
        $this->travelTo(now()->startOfSecond());

        $monitor = Monitor::factory()->for($this->user)->paused()->down(5)->create([
            'next_check_at' => now()->addHour(),
        ]);

        $this->postJson("/api/v1/monitors/{$monitor->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.paused_reason', null)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.consecutive_failures', 0);

        $this->assertTrue($monitor->fresh()->next_check_at->lte(now()));
    }

    public function test_resume_is_denied_when_active_limit_is_reached(): void
    {
        Monitor::factory()->for($this->user)->count(3)->create();
        $paused = Monitor::factory()->for($this->user)->paused()->create();

        $this->postJson("/api/v1/monitors/{$paused->id}/resume")->assertForbidden();

        $this->assertFalse($paused->fresh()->is_active);
    }

    public function test_user_cannot_pause_or_resume_another_users_monitor(): void
    {
        $active = Monitor::factory()->create();
        $paused = Monitor::factory()->paused()->create();

        $this->postJson("/api/v1/monitors/{$active->id}/pause")->assertNotFound();
        $this->postJson("/api/v1/monitors/{$paused->id}/resume")->assertNotFound();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse($paused->fresh()->is_active);
    }

    public function test_paused_monitor_status_is_preserved_until_resume(): void
    {
        $monitor = Monitor::factory()->for($this->user)->down()->create();

        $this->postJson("/api/v1/monitors/{$monitor->id}/pause")->assertOk();

        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);
    }
}
