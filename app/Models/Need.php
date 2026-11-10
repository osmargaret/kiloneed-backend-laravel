<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Need extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'text',
        'location',
        'country',
        'state',
        'city',
        'latitude',
        'longitude',
        'radius',
        'categories',
        'privacy',
        'expires_at',
        'clicks',
        'responses',
        'mentions',
        'saves_count',
        'shares_count',
        'reposts_count',
        'recos_count',
        'status',
    ];

    protected $casts = [
        'categories' => 'array',
        'expires_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'clicks' => 'integer',
        'responses' => 'integer',
        'mentions' => 'integer',
        'saves_count' => 'integer',
        'shares_count' => 'integer',
        'reposts_count' => 'integer',
        'recos_count' => 'integer',
    ];

    protected $appends = [
        'author',
        'authorId',
        'avatar',
        'avatarUrl',
        'postedAt',
        'expiresInText',
        'analytics',
        'saves',
        'shares',
        'reposts',
        'recos',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(NeedMedia::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function chats(): HasMany
    {
        return $this->hasMany(NeedChat::class)->oldest();
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function allReposts(): HasMany
    {
        return $this->hasMany(Repost::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    // Appended accessors matching frontend Need interface
    public function getAuthorAttribute(): string
    {
        return $this->user ? $this->user->name : 'Kiloneed Member';
    }

    public function getAuthorIdAttribute(): int|string
    {
        return $this->user_id;
    }

    public function getAvatarAttribute(): string
    {
        $name = $this->getAuthorAttribute();
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= mb_substr($p, 0, 1);
        }
        return strtoupper($initials) ?: 'KN';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->user?->avatar;
    }

    public function getPostedAtAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : 'Just now';
    }

    public function getExpiresInTextAttribute(): string
    {
        if (!$this->expires_at) {
            return 'No expiration';
        }

        if (Carbon::now()->greaterThan($this->expires_at)) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans(null, true) . ' left';
    }

    public function getAnalyticsAttribute(): array
    {
        return [
            'clicks' => $this->clicks,
            'responses' => $this->responses,
            'mentions' => $this->mentions,
        ];
    }

    public function getSavesAttribute(): int
    {
        return (int) $this->saves_count;
    }

    public function getSharesAttribute(): int
    {
        return (int) $this->shares_count;
    }

    public function getRepostsAttribute(): int
    {
        return (int) $this->reposts_count;
    }

    public function getRecosAttribute(): int
    {
        return (int) $this->recos_count;
    }
}

