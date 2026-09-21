<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Role extends Model
{
    protected $table = 'alma_auth_roles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('alma-auth.user_model'),
            'alma_auth_user_roles',
            'role_id',
            'user_id',
        );
    }
}
