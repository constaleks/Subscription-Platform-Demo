<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('error_type')->nullable();
            $table->string('error_message')->nullable();
            $table->timestampTz('down_notified_at')->nullable();
            $table->timestampTz('recovery_notified_at')->nullable();

            $table->index(['monitor_id', 'started_at']);
        });
        
        /*
         * Additional guard on DB level
         * A monitor can never have two open incidents at the same time
         */
        DB::statement('CREATE UNIQUE INDEX incidents_one_open_per_monitor ON incidents (monitor_id) WHERE resolved_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
