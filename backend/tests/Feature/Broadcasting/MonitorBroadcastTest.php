<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\ErrorType;
use App\Enums\MonitorStatus;
use App\Enums\PauseReason;
use App\Events\MonitorChecked;
use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\Services\MonitorChecker;
use App\Support\CheckResult;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MonitorBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Monitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.failure_threshold' => 2]);
        Notification::fake();

        $this->monitor = Monitor::factory()->create(['status' => MonitorStatus::Up]);
    }

    /** The checker is the only part that touches the network, so it is replaced by canned results. */
    private function fakeChecks(CheckResult ...$results): void
    {
        $this->mock(MonitorChecker::class, function (MockInterface $mock) use ($results) {
            $mock->shouldReceive('check')->times(count($results))->andReturn(...$results);
        });
    }

    private function failure(): CheckResult
    {
        return CheckResult::down(ErrorType::Timeout, 'Operation timed out');
    }

    private function success(): CheckResult
    {
        return CheckResult::up(200, 120);
    }

    private function runCheck(): void
    {
        CheckMonitorJob::dispatchSync($this->monitor);
        $this->travel(1)->minutes();
    }

    public function test_every_recorded_check_is_broadcast(): void
    {
        Event::fake([MonitorChecked::class]);

        // up, still up after one failure, down (incident opened), up again (incident resolved)
        $this->fakeChecks($this->success(), $this->failure(), $this->failure(), $this->success());

        foreach (range(1, 4) as $_) {
            $this->runCheck();
        }

        Event::assertDispatchedTimes(MonitorChecked::class, 4);

        $statuses = [];
        Event::assertDispatched(MonitorChecked::class, function (MonitorChecked $event) use (&$statuses) {
            $statuses[] = $event->monitor->status;

            return true;
        });

        // The status changes are the updates the dashboard needs most
        $this->assertSame(
            [MonitorStatus::Up, MonitorStatus::Up, MonitorStatus::Down, MonitorStatus::Up],
            $statuses,
        );
    }

    public function test_check_discarded_by_pause_is_not_broadcast(): void
    {
        Event::fake([MonitorChecked::class]);

        // Simulates the user pausing while the HTTP request is in flight
        $this->mock(MonitorChecker::class, function (MockInterface $mock) {
            $mock->shouldReceive('check')->once()->andReturnUsing(function (Monitor $monitor) {
                Monitor::find($monitor->id)->pause(PauseReason::User);

                return $this->failure();
            });
        });

        $this->runCheck();

        Event::assertNotDispatched(MonitorChecked::class);
    }

    public function test_event_is_broadcast_to_the_owner_with_api_shaped_payload(): void
    {
        $check = MonitorCheck::create([
            'monitor_id' => $this->monitor->id,
            'is_up' => true,
            'http_status_code' => 200,
            'response_time_ms' => 120,
            'checked_at' => now(),
        ]);

        $event = new MonitorChecked($this->monitor, $check);

        $this->assertSame(["private-users.{$this->monitor->user_id}"], array_map('strval', $event->broadcastOn()));
        $this->assertSame('monitor.checked', $event->broadcastAs());

        $payload = $event->broadcastWith();

        $this->assertSame($this->monitor->id, $payload['monitor']['id']);
        $this->assertSame($check->id, $payload['check']['id']);
        // Same resource as the REST API, so secrets stay out of the socket too
        $this->assertArrayNotHasKey('request_headers', $payload['monitor']);
    }

    /**
     * Tests run with the null broadcaster, which authorizes nothing. Switch to Reverb
     * (no network needed: auth only signs locally) and register the channels on it.
     */
    private function useReverbBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        app(BroadcastManager::class)->forgetDrivers();

        require base_path('routes/channels.php');
    }

    private function authorizeChannel(string $channel)
    {
        return $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    public function test_user_can_join_own_channel(): void
    {
        $this->useReverbBroadcaster();
        $user = $this->monitor->user;

        $this->actingAs($user);

        $this->authorizeChannel("private-users.{$user->id}")
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_user_cannot_join_another_users_channel(): void
    {
        $this->useReverbBroadcaster();
        $this->actingAs(User::factory()->create());

        $this->authorizeChannel("private-users.{$this->monitor->user_id}")->assertForbidden();
    }

    public function test_guest_cannot_authorize_channels(): void
    {
        $this->useReverbBroadcaster();

        $this->authorizeChannel("private-users.{$this->monitor->user_id}")->assertUnauthorized();
    }
}
