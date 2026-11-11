<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;

    public function __construct(array $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if (!empty($this->message['receiver_id'])) {
            $channels[] = new PrivateChannel('user.' . $this->message['receiver_id']);
        }

        if (!empty($this->message['need_id'])) {
            $channels[] = new Channel('need.' . $this->message['need_id']);
        }

        $channels[] = new Channel('chat-global');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}

