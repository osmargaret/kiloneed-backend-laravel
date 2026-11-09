<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'text',
        'media_url',
        'media_type',
    ];

    protected $appends = [
        'senderName',
        'senderAvatar',
        'time',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getSenderNameAttribute(): string
    {
        return $this->sender ? $this->sender->name : 'User';
    }

    public function getSenderAvatarAttribute(): ?string
    {
        return $this->sender?->avatar;
    }

    public function getTimeAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'Just now';
    }
}

