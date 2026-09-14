<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index()
    {
        $monitors = Monitor::orderBy('last_checked_at', 'desc')->get();
        return response()->json($monitors);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'check_interval' => 'integer|min:1'
        ]);

        $monitor = Monitor::create($validated);

        return response()->json(['message' => 'Monitor eklendi', 'monitor' => $monitor], 201);
    }

    public function destroy($id)
    {
        $monitor = Monitor::findOrFail($id);
        $monitor->delete(); 
        
        return response()->json(['message' => 'Monitor silindi'], 200);
    }

    public function checkAll()
    {
        \Illuminate\Support\Facades\Artisan::call('uptime:check');
        return response()->json(['message' => 'Tüm sitelere ping atıldı']);
    }
}
