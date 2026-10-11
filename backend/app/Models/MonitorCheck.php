<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\ErrorType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monitor_id', 'is_up', 'http_status_code', 'response_time_ms', 'error_type', 'error_message', 'checked_at'])]
class MonitorCheck extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'error_type' => ErrorType::class,
            'checked_at' => 'datetime',
        ];
    }
 
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
