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
