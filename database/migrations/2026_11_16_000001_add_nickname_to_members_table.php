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
        // Stored protected, like the name. A Member with a nickname is shown by it, so that two Members
        // with the same name can be told apart.
        Schema::table('members', function (Blueprint $table) {
            $table->text('nickname')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });
    }
};
