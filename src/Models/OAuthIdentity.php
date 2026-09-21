<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class OAuthIdentity extends Model
{
    protected $table = 'alma_auth_oauth_identities';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'linked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
        ];
    }
}
