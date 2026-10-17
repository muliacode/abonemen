<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SPIKE (#12): spatie/laravel-ciphersweet's table with ULID morphs instead of integer ids.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blind_indexes', function (Blueprint $table) {
            $table->ulidMorphs('indexable');
            $table->string('name');
            $table->string('value');

            $table->index(['name', 'value']);
            $table->unique(['indexable_type', 'indexable_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blind_indexes');
    }
};
