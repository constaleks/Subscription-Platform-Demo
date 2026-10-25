<?php

namespace Tests\Feature\Incidents;

use App\Enums\ErrorType;
use App\Enums\MonitorStatus;
use App\Jobs\CheckMonitorJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Notifications\IncidentOpened;
use App\Notifications\IncidentResolved;
use App\Services\MonitorChecker;
use App\Support\CheckResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class IncidentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Monitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.failure_threshold' => 2]);
        $this->travelTo(now()->startOfSecond());

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

    /** Runs one check through the real queue path (serialization, middleware, model reload). */
    private function runCheck(): void
    {
        CheckMonitorJob::dispatchSync($this->monitor);
        $this->travel(1)->minutes();
    }

    public function test_single_failure_below_threshold_does_not_open_incident(): void
    {
        Notification::fake();
        $this->fakeChecks($this->failure());

        $this->runCheck();

        $this->monitor->refresh();
        $this->assertSame(MonitorStatus::Up, $this->monitor->status);
        $this->assertSame(1, $this->monitor->consecutive_failures);
        $this->assertSame(0, Incident::count());
        Notification::assertNothingSent();
    }

    public function test_reaching_threshold_opens_incident_and_notifies_owner(): void
    {
        Notification::fake();
        $this->fakeChecks($this->failure(), $this->failure());

        $firstFailureAt = now()->copy();
        $this->runCheck();
        $this->runCheck();

        $this->assertSame(MonitorStatus::Down, $this->monitor->fresh()->status);

        $incident = Incident::sole();
        $this->assertNull($incident->resolved_at);
        $this->assertSame(ErrorType::Timeout, $incident->error_type);
        $this->assertSame('Operation timed out', $incident->error_message);
        // The outage began at the first failed check, not the one that crossed the threshold
        $this->assertTrue($incident->started_at->equalTo($firstFailureAt));

        Notification::assertSentTo(
            $this->monitor->user,
            IncidentOpened::class,
            fn (IncidentOpened $notification) => $notification->incident->is($incident),
        );
        Notification::assertSentTimes(IncidentOpened::class, 1);
    }

    public function test_further_failures_while_down_do_not_open_another_incident(): void
    {
        Notification::fake();
        $this->fakeChecks($this->failure(), $this->failure(), $this->failure(), $this->failure());

        foreach (range(1, 4) as $_) {
            $this->runCheck();
        }

        $this->assertSame(1, Incident::count());
        $this->assertSame(4, $this->monitor->fresh()->consecutive_failures);
        Notification::assertSentTimes(IncidentOpened::class, 1);
    }

    public function test_recovery_resolves_incident_and_notifies_owner(): void
    {
        Notification::fake();
        $this->fakeChecks($this->failure(), $this->failure(), $this->success());

        $this->runCheck();
        $this->runCheck();
        $recoveredAt = now()->copy();
        $this->runCheck();

        $monitor = $this->monitor->fresh();
        $this->assertSame(MonitorStatus::Up, $monitor->status);
        $this->assertSame(0, $monitor->consecutive_failures);

        $incident = Incident::sole();
        $this->assertTrue($incident->resolved_at->equalTo($recoveredAt));

        Notification::assertSentTo(
            $monitor->user,
            IncidentResolved::class,
            fn (IncidentResolved $notification) => $notification->incident->is($incident),
        );
    }

    public function test_paused_monitor_is_not_checked(): void
    {
        Notification::fake();
        $this->monitor->pause(\App\Enums\PauseReason::User);
        $this->fakeChecks(); // check() must never be called

        $this->runCheck();

        $this->assertSame(0, $this->monitor->checks()->count());
        Notification::assertNothingSent();
    }

    public function test_monitor_paused_during_check_discards_result(): void
    {
        Notification::fake();
        $this->monitor->forceFill(['consecutive_failures' => 1])->save();

        // Simulates the user pausing while the HTTP request is in flight
        $this->mock(MonitorChecker::class, function (MockInterface $mock) {
            $mock->shouldReceive('check')->once()->andReturnUsing(function (Monitor $monitor) {
                Monitor::find($monitor->id)->pause(\App\Enums\PauseReason::User);

                return $this->failure();
            });
        });

        $this->runCheck();

        $monitor = $this->monitor->fresh();
        $this->assertFalse($monitor->is_active);
        $this->assertSame(1, $monitor->consecutive_failures);
        $this->assertSame(0, $monitor->checks()->count());
        $this->assertSame(0, Incident::count());
        Notification::assertNothingSent();
    }

    public function test_emails_are_delivered_and_incident_is_marked_notified(): void
    {
        // No Notification::fake(): real mail channel (array mailer) and the MarkIncidentNotified listener
        $this->fakeChecks($this->failure(), $this->failure(), $this->success());

        $this->runCheck();
        $this->runCheck();

        $incident = Incident::sole();
        $this->assertNotNull($incident->down_notified_at);
        $this->assertNull($incident->recovery_notified_at);

        $this->runCheck();

        $incident->refresh();
        $this->assertNotNull($incident->recovery_notified_at);

        $subjects = $this->sentMessages()->map(fn ($message) => $message->getOriginalMessage()->getSubject());
        $this->assertEquals([
            "[Down] {$this->monitor->name} is not responding",
            "[Up] {$this->monitor->name} is back up",
        ], $subjects->all());
    }

    public function test_notification_is_not_sent_twice_for_the_same_incident(): void
    {
        $incident = Incident::create([
            'monitor_id' => $this->monitor->id,
            'started_at' => now()->subMinutes(5),
            'error_type' => ErrorType::Timeout,
        ]);

        $this->monitor->user->notify(new IncidentOpened($incident));
        $this->monitor->user->notify(new IncidentOpened($incident)); // e.g. a retried job

        $this->assertCount(1, $this->sentMessages());
    }

    public function test_down_email_contains_reason_and_start_time(): void
    {
        $incident = Incident::create([
            'monitor_id' => $this->monitor->id,
            'started_at' => now()->setDate(2026, 10, 25)->setTime(14, 30),
            'error_type' => ErrorType::UnexpectedStatus,
            'error_message' => 'Expected 2xx, got 503',
        ]);

        $mail = (new IncidentOpened($incident))->toMail($this->monitor->user);
        $lines = implode("\n", $mail->introLines);

        $this->assertStringContainsString('Unexpected status code: Expected 2xx, got 503', $lines);
        $this->assertStringContainsString('Oct 25, 2026 14:30 UTC', $lines);
        $this->assertSame(config('monitoring.frontend_url'), $mail->actionUrl);
    }

    private function sentMessages()
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }
}
