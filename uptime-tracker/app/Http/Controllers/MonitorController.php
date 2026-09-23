<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\Request;
use App\Http\Requests\StoreMonitorRequest;

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

    public function checkAll()
    {
        \Illuminate\Support\Facades\Artisan::call('uptime:check');
        return response()->json(['message' => 'Tüm sitelere ping atıldı']);
    }
}
