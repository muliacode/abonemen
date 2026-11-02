<?php

use App\Actions\Households\SyncDefaultRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(SyncDefaultRoles::class)->handle();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
