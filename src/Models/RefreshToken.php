<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class RefreshToken extends Model
{
    protected $table = 'alma_auth_refresh_tokens';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'family_id',
        'family_created_at',
        'token_hash',
        'expires_at',
        'revoked',
        'device_fingerprint',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'family_created_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    public function isValid(): bool
    {
        return ! $this->revoked && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
