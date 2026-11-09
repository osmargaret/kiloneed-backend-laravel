<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeedChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'need_id',
        'sender_id',
        'recipient_id',
        'text',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected $appends = [
        'sender',
        'senderAvatar',
        'time',
    ];

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function getSenderAttribute(): string
    {
        return $this->senderUser ? $this->senderUser->name : 'User';
    }

    public function getSenderAvatarAttribute(): ?string
    {
        return $this->senderUser?->avatar;
    }

    public function getTimeAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'Just now';
    }
}

