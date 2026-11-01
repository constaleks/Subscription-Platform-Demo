<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorCheckResource extends JsonResource
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
            'is_up' => $this->is_up,
            'http_status_code' => $this->http_status_code,
            'response_time_ms' => $this->response_time_ms,
            'error_type' => $this->error_type,
            'error_type_label' => $this->error_type?->label(),
            'error_message' => $this->error_message,
            'checked_at' => $this->checked_at,
        ];
    }
}
