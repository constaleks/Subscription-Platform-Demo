<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\HttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\PauseReason;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'url', 'method', 'expected_status_code', 'request_headers', 'interval_seconds', 'timeout_seconds'])]
class Monitor extends Model
{
    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'status' => MonitorStatus::class,
            'paused_reason' => PauseReason::class,
            'request_headers' => 'encrypted:array',
            'is_active' => 'boolean',
            'next_check_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }
 
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
 
    public function openIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->whereNull('resolved_at');
    }

    /*
     * Keep is_active and paused_reason consistent, and close any open
     * incident (a paused monitor is never checked, so it could not resolve).
     */
    public function pause(PauseReason $reason): void
    {
        DB::transaction(function () use ($reason) {
            // forceFill: these columns are intentionally not mass-assignable
            $this->forceFill([
                'is_active' => false,
                'paused_reason' => $reason,
            ])->save();

            $this->openIncident()->update(['resolved_at' => now()]);
        });
    }

    public function resume(): void
    {
        $this->forceFill([
            'is_active' => true,
            'paused_reason' => null,
            'next_check_at' => now(),
            'status' => MonitorStatus::Pending,
            'consecutive_failures' => 0,
        ])->save();
    }
}
