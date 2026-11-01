<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Http\Resources\MonitorResource;
use App\Http\Resources\MonitorCheckResource;

class MonitorChecked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Monitor $monitor, public MonitorCheck $check) { }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("users.{$this->monitor->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }

    public function broadcastWith(): array
    {
        return [
            'monitor' => MonitorResource::make($this->monitor)->resolve(),
            'check' => MonitorCheckResource::make($this->check)->resolve(),
        ];
    }
}
