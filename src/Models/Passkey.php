<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class Passkey extends Model
{
    protected $table = 'alma_auth_passkeys';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'credential_id',
        'credential_id_hash',
        'public_key',
        'counter',
        'name',
        'transports',
        'aaguid',
        'user_handle',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'counter' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
