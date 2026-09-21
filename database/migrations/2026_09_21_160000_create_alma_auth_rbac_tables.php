<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alma_auth_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('alma_auth_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('alma_auth_roles')->cascadeOnDelete();
            $table->string('resource', 64);
            $table->string('action', 64);
            $table->string('scope', 16)->default('own'); // own | any
            $table->timestamps();
            $table->unique(['role_id', 'resource', 'action', 'scope'], 'alma_rbac_perm_unique');
        });

        Schema::create('alma_auth_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('role_id')->constrained('alma_auth_roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alma_auth_user_roles');
        Schema::dropIfExists('alma_auth_role_permissions');
        Schema::dropIfExists('alma_auth_roles');
    }
};
