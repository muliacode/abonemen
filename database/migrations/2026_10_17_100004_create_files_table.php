<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encrypted uploads, such as logos. The file itself is encrypted on the
     * "encrypted" disk; this row holds only operational data.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('household_id', 26)->index();
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->unsignedInteger('key_version');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
