<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ULID keys, so encrypted fields are bound to their row before insert.
        // Name and email hold ciphertext under the global data key; email is
        // found and kept unique through its blind index (blind_indexes table).
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->text('name');
            $table->text('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // No password_reset_tokens table: Laravel's keys its tokens by the
        // email in plaintext. Password reset (#31) brings a store keyed by
        // user id instead.

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
