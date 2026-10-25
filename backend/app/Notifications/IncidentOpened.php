<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Incident;

class IncidentOpened extends Notification implements ShouldQueue
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
 
        $reason = $incident->error_type?->label() ?? 'Unknown';
 
        if ($incident->error_message) {
            $reason .= ": {$incident->error_message}";
        }
 
        return (new MailMessage)
            ->subject("[Down] {$monitor->name} is not responding")
            ->greeting('Your monitor is down')
            ->line("We could not reach {$monitor->name} ({$monitor->url}).")
            ->line('Down since: ' . $incident->started_at->format('M j, Y H:i') . ' UTC')
            ->line("Reason: {$reason}")
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
        return $this->incident->down_notified_at === null;
    }
}
