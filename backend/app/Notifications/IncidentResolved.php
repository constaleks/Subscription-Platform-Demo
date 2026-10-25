<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Incident;

class IncidentResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
 
    public array $backoff = [10, 30, 60];
 
    public bool $deleteWhenMissingModels = true; // Incident deleted (monitor removed) before the email was sent: drop silently

    /**
     * Create a new notification instance.
     */
    public function __construct(public Incident $incident)
    {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $incident = $this->incident;
        $monitor = $incident->monitor;
 
        return (new MailMessage)
            ->subject("[Up] {$monitor->name} is back up")
            ->greeting('Your monitor has recovered')
            ->line("{$monitor->name} ({$monitor->url}) is responding again.")
            ->line('Down since: ' . $incident->started_at->format('M j, Y H:i') . ' UTC')
            ->line('Recovered: ' . $incident->resolved_at->format('M j, Y H:i') . ' UTC')
            ->line('Total downtime: ' . $incident->durationForHumans())
            ->action('View monitor', config('monitoring.frontend_url'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    /** Idempotency: a retried job must never send the same email twice. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->incident->recovery_notified_at === null;
    }
}
