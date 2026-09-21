<?php

declare(strict_types=1);

namespace Alma\Auth\Models;

use Illuminate\Database\Eloquent\Model;

final class UserRole extends Model
{
    protected $table = 'alma_auth_user_roles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'role_id',
    ];
}
