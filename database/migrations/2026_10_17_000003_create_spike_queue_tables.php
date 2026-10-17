<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SPIKE (#15): records for the cron-mode queue test.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spike_queue_probes', function (Blueprint $table) {
            $table->unsignedInteger('number')->primary();
            $table->timestamp('queued_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('worker_pid')->nullable();
        });

        Schema::create('spike_cron_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->unsignedInteger('pending_at_start');
            $table->string('php_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spike_queue_probes');
        Schema::dropIfExists('spike_cron_runs');
    }
};
