<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every data key is also sealed to the public half of the recovery key, so
     * either root key alone can restore it.
     *
     * Nullable only because SQLite cannot add a NOT NULL column without a
     * default; DataKeyRing always sets it, and recovery reports rows without it.
     */
    public function up(): void
    {
        Schema::table('data_keys', function (Blueprint $table) {
            $table->text('wrapped_rk')->nullable()->after('wrapped_mk');
        });
    }

    public function down(): void
    {
        Schema::table('data_keys', function (Blueprint $table) {
            $table->dropColumn('wrapped_rk');
        });
    }
};
