<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Monitor;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\MonitorCheckResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MonitorCheckController extends Controller
{
    public function index(Request $request, Monitor $monitor): AnonymousResourceCollection
    {
        Gate::authorize('view', $monitor);

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $checks = $monitor->checks()
            ->where('checked_at', '>=', now()->subDays($request->user()->plan()->retentionDays()))
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->cursorPaginate($data['per_page'] ?? 50);

        return MonitorCheckResource::collection($checks);
    }
}
