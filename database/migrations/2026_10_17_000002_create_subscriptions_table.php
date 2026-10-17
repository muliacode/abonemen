<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SPIKE (#12): just enough of a subscription to measure decryption; the real table arrives with #46.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('household_id')->index();
            $table->text('name');
            $table->text('price');
            $table->text('currency');
            $table->text('notes')->nullable();
            $table->text('url')->nullable();
            $table->string('billing_cycle');
            $table->date('next_payment_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
