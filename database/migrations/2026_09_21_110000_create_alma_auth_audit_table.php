<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alma_auth_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seq')->unique();
            $table->string('event_type');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('actor_pseudonym');
            $table->text('payload');
            $table->string('envelope_hash', 64);
            $table->string('prev_hash', 64)->nullable();
            $table->string('ts_signed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alma_auth_audit');
    }
};
