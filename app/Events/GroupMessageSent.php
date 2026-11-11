<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $groupId;
    public array $post;

    public function __construct(int $groupId, array $post)
    {
        $this->groupId = $groupId;
        $this->post = $post;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('group.' . $this->groupId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'group.message';
    }
}
