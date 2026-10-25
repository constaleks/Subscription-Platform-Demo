<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Example',
            'url' => 'https://93.184.216.34',
            'interval_seconds' => 900,
            ...$overrides,
        ];
    }

    public function test_guest_cannot_access_monitors(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/monitors')->assertUnauthorized();
    }

    public function test_index_lists_only_own_monitors(): void
    {
        $own = Monitor::factory()->for($this->user)->create(['name' => 'Mine']);
        Monitor::factory()->create(['name' => 'Someone else']);

        $this->getJson('/api/v1/monitors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_user_can_create_monitor(): void
    {
        $this->postJson('/api/v1/monitors', $this->validPayload([
            'request_headers' => ['Authorization' => 'Bearer secret'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Example')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.request_header_names', ['Authorization'])
            ->assertJsonMissingPath('data.request_headers');

        $monitor = $this->user->monitors()->sole();

        $this->assertSame('Bearer secret', $monitor->request_headers['Authorization']);
        $this->assertNotNull($monitor->next_check_at);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->postJson('/api/v1/monitors', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'url', 'interval_seconds']);
    }

    public function test_create_rejects_private_urls(): void
    {
        $this->postJson('/api/v1/monitors', $this->validPayload(['url' => 'http://127.0.0.1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        $this->postJson('/api/v1/monitors', $this->validPayload(['url' => 'http://169.254.169.254/latest/meta-data']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_create_rejects_interval_not_in_plan(): void
    {
        // 300s is Pro only
        $this->postJson('/api/v1/monitors', $this->validPayload(['interval_seconds' => 300]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('interval_seconds');
    }

    public function test_create_is_denied_when_plan_limit_is_reached(): void
    {
        Monitor::factory()->for($this->user)->count(3)->create();

        $this->postJson('/api/v1/monitors', $this->validPayload())->assertForbidden();

        $this->assertSame(3, $this->user->monitors()->count());
    }

    public function test_user_can_view_own_monitor(): void
    {
        $monitor = Monitor::factory()->for($this->user)->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $monitor->id);
    }

    public function test_user_cannot_view_another_users_monitor(): void
    {
        $monitor = Monitor::factory()->create();

        $this->getJson("/api/v1/monitors/{$monitor->id}")->assertNotFound();
    }

    public function test_user_can_update_own_monitor(): void
    {
        $monitor = Monitor::factory()->for($this->user)->create(['interval_seconds' => 3600]);

        $this->putJson("/api/v1/monitors/{$monitor->id}", [
            'name' => 'Renamed',
            'interval_seconds' => 900,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.interval_seconds', 900);

        $this->assertSame('Renamed', $monitor->fresh()->name);
    }

    public function test_update_accepts_partial_payload(): void
    {
        $monitor = Monitor::factory()->for($this->user)->create(['name' => 'Old']);

        $this->patchJson("/api/v1/monitors/{$monitor->id}", ['name' => 'New'])->assertOk();

        $this->assertSame('New', $monitor->fresh()->name);
    }

    public function test_user_cannot_update_another_users_monitor(): void
    {
        $monitor = Monitor::factory()->create(['name' => 'Original']);

        $this->patchJson("/api/v1/monitors/{$monitor->id}", ['name' => 'Hacked'])->assertNotFound();

        $this->assertSame('Original', $monitor->fresh()->name);
    }

    public function test_user_can_delete_own_monitor(): void
    {
        $monitor = Monitor::factory()->for($this->user)->create();

        $this->deleteJson("/api/v1/monitors/{$monitor->id}")->assertNoContent();

        $this->assertModelMissing($monitor);
    }

    public function test_user_cannot_delete_another_users_monitor(): void
    {
        $monitor = Monitor::factory()->create();

        $this->deleteJson("/api/v1/monitors/{$monitor->id}")->assertNotFound();

        $this->assertModelExists($monitor);
    }
}
