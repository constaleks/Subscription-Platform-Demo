<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Models\Monitor;
use App\Services\MonitorStats;

class MonitorStatsController extends Controller
{
    public function __invoke(Request $request, Monitor $monitor, MonitorStats $stats): JsonResponse
    {
        Gate::authorize('view', $monitor);

        return response()->json([
            'data' => $stats->forMonitor($monitor, $request->user()->plan()->retentionDays()),
        ]);
    }
}
