<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $notification;

    public function __construct(array $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('chat-global')];

        if (!empty($this->notification['user_id'])) {
            $channels[] = new PrivateChannel('user.' . $this->notification['user_id']);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
