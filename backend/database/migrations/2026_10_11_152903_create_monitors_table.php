<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\HttpMethod;
use App\Enums\MonitorStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('url');
            $table->string('method')->default(HttpMethod::Get->value);
            $table->unsignedSmallInteger('expected_status_code')->nullable();
            $table->text('request_headers')->nullable();
            $table->unsignedInteger('interval_seconds');
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->timestampTz('next_check_at');
            $table->timestampTz('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('paused_reason')->nullable();
            $table->string('status')->default(MonitorStatus::Pending->value);
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestampsTz();

            $table->index('user_id');
            $table->index(['is_active', 'next_check_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
