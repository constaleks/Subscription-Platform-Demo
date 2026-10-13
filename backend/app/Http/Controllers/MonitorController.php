<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\MonitorResource;
use App\Http\Requests\SaveMonitorRequest;
use Illuminate\Http\JsonResponse;
use App\Models\Monitor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Response;
use App\Enums\PauseReason;

class MonitorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return MonitorResource::collection(
            $request->user()->monitors()->orderBy('name')->get()
        );
    }
 
    public function store(SaveMonitorRequest $request): JsonResponse
    {
        $monitor = $request->user()->monitors()->make($request->validated());
        $monitor->next_check_at = now();
        $monitor->save();
 
        return MonitorResource::make($monitor)->response()->setStatusCode(201);
    }
 
    public function show(Monitor $monitor): MonitorResource
    {
        Gate::authorize('view', $monitor);
 
        return MonitorResource::make($monitor);
    }
 
    public function update(SaveMonitorRequest $request, Monitor $monitor): MonitorResource
    {
        $monitor->fill($request->validated());
 
        if ($monitor->isDirty('interval_seconds')) {
            $monitor->next_check_at = min(
                $monitor->next_check_at,
                now()->addSeconds($monitor->interval_seconds),
            );
        }
 
        $monitor->save();
 
        return MonitorResource::make($monitor);
    }
 
    public function destroy(Monitor $monitor): Response
    {
        Gate::authorize('delete', $monitor);
 
        $monitor->delete();
 
        return response()->noContent();
    }
 
    public function pause(Monitor $monitor): MonitorResource
    {
        Gate::authorize('update', $monitor);
 
        $monitor->pause(PauseReason::User);
 
        return MonitorResource::make($monitor->fresh());
    }
 
    public function resume(Monitor $monitor): MonitorResource
    {
        Gate::authorize('resume', $monitor);
 
        $monitor->resume();
 
        return MonitorResource::make($monitor->fresh());
    }
}
