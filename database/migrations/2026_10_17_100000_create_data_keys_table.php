<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The global data key and the versioned household data keys, each wrapped
     * by the master key. See "Keys" in docs/product/technical-design.md.
     */
    public function up(): void
    {
        Schema::create('data_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('owner_type', 16);
            // The household's ULID, or "instance" for the global data key, so
            // the unique index below also holds for the global key.
            $table->string('owner_id', 26);
            $table->unsignedInteger('version');
            $table->text('wrapped_mk');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_keys');
    }
};
