<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorIncidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'started_at' => $this->started_at,
            'resolved_at' => $this->resolved_at,
            'is_ongoing' => $this->resolved_at === null,
            'duration_seconds' => $this->durationSeconds(),
            'error_type' => $this->error_type,
            'error_type_label' => $this->error_type?->label(),
            'error_message' => $this->error_message,
        ];
    }
}
