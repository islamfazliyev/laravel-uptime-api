<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Monitor;
use App\Models\Ping;
use Illuminate\Support\Facades\Http;

class CheckUptime extends Command
{
    protected $signature = 'uptime:check';
    protected $description = 'Veritabanındaki tüm URL lere ping atar ve durumu günceller.';


    public function handle()
    {
        $monitors = Monitor::all();

        foreach ($monitors as $monitor) {
            $startTime = microtime(true);

            try {
                $response = Http::timeout(10)->get($monitor->url);
                $statusCode = $response->status();
                $isUp = $response->successful(); // 200-299 arası dönüşler başarılı sayılır
            } catch (\Throwable $th) {
                $statusCode = null;
                $isUp = false;
            }

            $responseTime = round((microtime(true) - $startTime) * 1000);

            $monitor->update([
                'status' => $isUp ? 'up' : 'down',
                'last_checked_at' => now(),
            ]);

            Ping::create([
                'monitor_id' => $monitor->id,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
            ]);

            $this->info("Kontrol edildi: {$monitor->name} - Durum: " . ($isUp ? 'UP' : 'DOWN'));
        }
    }
}
