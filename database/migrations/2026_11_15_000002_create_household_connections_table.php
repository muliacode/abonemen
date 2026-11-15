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
        // One row is an Owner's consent to one Connection for a Household. No row means the Connection is off.
        Schema::create('household_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('connection');
            $table->foreignId('enabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // What the Owner was shown: where the data goes (an SMTP host or mailer name) and the disclosure version.
            $table->string('confirmed_destination');
            $table->unsignedSmallInteger('confirmed_version');
            $table->timestamp('enabled_at');
            $table->timestamps();

            $table->unique(['household_id', 'connection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('household_connections');
    }
};
