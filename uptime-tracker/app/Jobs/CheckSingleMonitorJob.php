<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\Ping;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Notifications\UptimeAlert;

class CheckSingleMonitorJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable, SerializesModels;

    // Monitor was deleted while the job was waiting in the queue -> just drop the job
    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    // Same monitor can't be queued twice within 60 seconds
    public int $uniqueFor = 60;

    public function __construct(public Monitor $monitor)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->monitor->id;
    }

    public function handle(): void
    {
        $monitor = $this->monitor;
        $oldState = $monitor->status;
        $startTime = microtime(true);

        $statusCode = null;
        $isUp = false;

        try {
            $response = Http::timeout(10)->get($monitor->url);
            $statusCode = $response->status();
            $isUp = $response->successful();

            if ($isUp && $monitor->keyword) {
                $isUp = !str_contains($response->body(), $monitor->keyword);
            }
        } catch (\Throwable $th) {
            $statusCode = null;
            $isUp = false;
        }

        $responseTime = (int) round((microtime(true) - $startTime) * 1000);

        // Alert only when the state changes
        if (!$isUp && $oldState !== 'down') {
            $this->notify($statusCode
                ? "🚨 **WARNING:** {$monitor->url} is returning an error! (Status Code: {$statusCode})"
                : "🔥 **CRITICAL OUTAGE:** {$monitor->url} is unreachable! (Server down or timeout)");
            $monitor->notify(new UptimeAlert($monitor, false, $statusCode));
        } elseif ($isUp && $oldState === 'down') {
            $this->notify("✅ **RESOLVED:** {$monitor->url} is back online!");
            $monitor->notify(new UptimeAlert($monitor, true, null));
        }

        $monitor->update([
            'status' => $isUp ? 'up' : 'down',
            'last_checked_at' => now(),
        ]);

        Ping::create([
            'monitor_id' => $monitor->id,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTime,
        ]);
    }

    private function notify(string $message): void
    {
        $webhook = env('DISCORD_ALERT_WEBHOOK');

        if (!$webhook) {
            return;
        }

        try {
            $response = Http::timeout(5)->post($webhook, ['content' => $message]);
        } catch (\Throwable $th) {
                report($th);
        }
    }
}
