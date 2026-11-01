<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Monitor;
use App\Http\Resources\MonitorIncidentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MonitorIncidentController extends Controller
{
    public function index(Request $request, Monitor $monitor): AnonymousResourceCollection
    {
        Gate::authorize('view', $monitor);

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $incidents = $monitor->incidents()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return MonitorIncidentResource::collection($incidents);
    }
}
