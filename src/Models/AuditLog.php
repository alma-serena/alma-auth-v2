<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    protected $table = 'alma_auth_audit';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'user_id',
        'actor_pseudonym',
        'payload',
        'envelope_hash',
        'prev_hash',
        'seq',
        'ts_signed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'seq' => 'integer',
        ];
    }
}
