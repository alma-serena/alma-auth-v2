<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RolePermission extends Model
{
    protected $table = 'alma_auth_role_permissions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'resource',
        'action',
        'scope',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
