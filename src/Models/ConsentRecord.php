<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class ConsentRecord extends Model
{
    protected $table = 'alma_auth_consents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'purpose',
        'policy_version',
        'ip_hash',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }
}
