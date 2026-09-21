<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class EmailChangeRequest extends Model
{
    protected $table = 'alma_auth_email_changes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'new_email',
        'code_hash',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
