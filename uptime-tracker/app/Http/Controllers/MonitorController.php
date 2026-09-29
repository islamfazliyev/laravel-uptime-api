<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use App\Jobs\CheckSingleMonitorJob;
use App\Models\Monitor;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index(Request $request)
    {
        $monitors = $request->user()->monitors()->orderBy('last_checked_at', 'desc')->get();
        return response()->json($monitors);
    }

    public function store(StoreMonitorRequest $request)
    {
        $validated = $request->validated();

        $monitor = $request->user()->monitors()->create($validated);

        return response()->json(['message' => 'Monitor eklendi', 'monitor' => $monitor], 201);
    }

    public function destroy(Request $request, $id)
    {
        $monitor = Monitor::where('user_id', $request->user()->id)->findOrFail($id);
        $monitor->delete();

        return response()->json(['message' => 'Monitor Deleted'], 200);
    }

    // Manual "check now": only the caller's monitors, queued, returns immediately
    public function checkAll(Request $request)
    {
        $monitors = $request->user()->monitors;

        foreach ($monitors as $monitor) {
            CheckSingleMonitorJob::dispatch($monitor);
        }

        return response()->json([
            'message' => 'Checks queued',
            'count' => $monitors->count(),
        ], 202);
    }

    public function stats(Request $request, $id)
    {
        $monitor = Monitor::where('user_id', $request->user()->id)->findOrFail($id);

        $since = now()->subDay();

        $total = $monitor->pings()->where('created_at', '>=', $since)->count();
        $up = $monitor->pings()
            ->where('created_at', '>=', $since)
            ->whereBetween('status_code', [200, 299])
            ->count();

        $recent = $monitor->pings()
            ->latest()
            ->take(20)
            ->get(['status_code', 'response_time_ms', 'created_at'])
            ->reverse()
            ->values();

        return response()->json([
            'uptime_24h' => $total > 0 ? round($up / $total * 100, 2) : null,
            'total_checks_24h' => $total,
            'recent' => $recent,
        ]);
    }
}
