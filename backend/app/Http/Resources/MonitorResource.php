<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
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
            'name' => $this->name,
            'url' => $this->url,
            'method' => $this->method,
            'expected_status_code' => $this->expected_status_code,
            'request_header_names' => array_keys($this->request_headers ?? []),
            'interval_seconds' => $this->interval_seconds,
            'timeout_seconds' => $this->timeout_seconds,
            'is_active' => $this->is_active,
            'paused_reason' => $this->paused_reason,
            'status' => $this->status,
            'consecutive_failures' => $this->consecutive_failures,
            'last_checked_at' => $this->last_checked_at,
            'next_check_at' => $this->next_check_at,
            'created_at' => $this->created_at,
        ];
    }
}
