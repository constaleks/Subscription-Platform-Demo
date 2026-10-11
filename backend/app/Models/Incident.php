<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\ErrorType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['monitor_id', 'started_at', 'resolved_at', 'error_type', 'error_message', 'down_notified_at', 'recovery_notified_at'])]
class Incident extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'error_type' => ErrorType::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'down_notified_at' => 'datetime',
            'recovery_notified_at' => 'datetime',
        ];
    }
 
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
 
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
