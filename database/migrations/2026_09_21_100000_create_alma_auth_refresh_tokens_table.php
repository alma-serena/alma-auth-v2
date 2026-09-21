<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alma_auth_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('family_id')->index();
            $table->timestamp('family_created_at')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->string('device_fingerprint')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alma_auth_refresh_tokens');
    }
};
