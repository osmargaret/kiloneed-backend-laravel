<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'description',
        'detail',
        'from_user_id',
        'need_id',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    protected $appends = [
        'from',
        'time',
        'read',
    ];

    protected static function booted(): void
    {
        static::created(function (Notification $notification) {
            try {
                $notification->loadMissing('fromUser');
                broadcast(new \App\Events\NotificationCreated([
                    'id' => (string) $notification->id,
                    'user_id' => (int) $notification->user_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'description' => $notification->description ?: ($notification->message ?? ''),
                    'detail' => $notification->detail ?: ($notification->message ?? $notification->description ?? ''),
                    'from' => $notification->from,
                    'time' => 'Just now',
                    'read' => false,
                ]));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Notification broadcast failed: ' . $e->getMessage());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }

    public function getFromAttribute(): string
    {
        return $this->fromUser ? $this->fromUser->name : 'System';
    }

    public function getTimeAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'Just now';
    }

    public function getReadAttribute(): bool
    {
        return (bool) $this->is_read;
    }
}
