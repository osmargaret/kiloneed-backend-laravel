<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'need_id',
        'user_id',
        'text',
    ];

    protected $appends = [
        'author',
        'avatar',
        'time',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }

    public function getAuthorAttribute(): string
    {
        return $this->user ? $this->user->name : 'User';
    }

    public function getAvatarAttribute(): ?string
    {
        return $this->user?->avatar;
    }

    public function getTimeAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'Just now';
    }
}

