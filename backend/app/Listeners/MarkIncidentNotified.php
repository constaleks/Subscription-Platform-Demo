<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\IncidentOpened;
use App\Notifications\IncidentResolved;
use Illuminate\Notifications\Events\NotificationSent;

class MarkIncidentNotified
{
    /**
     * Create the event listener.
     */
    public function __construct() { }

    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        $notification = $event->notification;
 
        if ($notification instanceof IncidentOpened) {
            $notification->incident->update(['down_notified_at' => now()]);
        } elseif ($notification instanceof IncidentResolved) {
            $notification->incident->update(['recovery_notified_at' => now()]);
        }
    }
}
