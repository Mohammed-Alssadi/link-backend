<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'authorization_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasExpired(): bool
    {
        if (empty($this->expires_at)) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function isAboutToExpire(int $marginSeconds = 300): bool
    {
        if (empty($this->expires_at)) {
            return false;
        }

        return now()->addSeconds($marginSeconds)->greaterThanOrEqualTo($this->expires_at);
    }
}
