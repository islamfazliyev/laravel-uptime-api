<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\Ping;
use App\Notifications\UptimeAlert;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckSingleMonitorJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable, SerializesModels;

    // Monitor was deleted while the job was waiting in the queue -> just drop the job
    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    // Same monitor can't be queued twice within 60 seconds
    public int $uniqueFor = 60;

    public int $retryDelaySeconds = 2;

    public int $timeoutSeconds = 5;

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

        [$isUp, $statusCode, $responseTime] = $this->check($monitor);

        // False-Positive Protection: If the request fails or times out,
        // wait 2 seconds and perform a secondary verification check before marking as down.
        if (! $isUp) {
            sleep($this->retryDelaySeconds);
            [$isUp, $statusCode, $responseTime] = $this->check($monitor);
        }

        $currentState = 'down';
        if ($isUp) {
            $currentState = ($responseTime > $monitor->max_response_time_ms) ? 'degraded' : 'up';
        }
        // Alert only when the state changes
        if ($currentState === 'down' && $oldState !== 'down') {
            $this->notify($statusCode
                ? "🚨 **WARNING:** {$monitor->url} is returning an error! (Status Code: {$statusCode})"
                : "🔥 **CRITICAL OUTAGE:** {$monitor->url} is unreachable! (Server down or timeout)");
            $monitor->notify(new UptimeAlert($monitor, false, $statusCode));
        } elseif ($currentState === 'degraded' && $oldState !== 'degraded') {
            $this->notify("⚠️ **DEGRADED PERFORMANCE:** {$monitor->url} works very slow. response time: {$responseTime}ms (Limit: {$monitor->max_response_time_ms}ms)");
            
        } elseif ($currentState === 'up' && $oldState !== 'up') {
            $this->notify("✅ **RESOLVED:** {$monitor->url} is back online!");
            $monitor->notify(new UptimeAlert($monitor, true, null));
        }

        $monitor->update([
            'status' => $currentState,
            'last_checked_at' => now(),
        ]);

        Ping::create([
            'monitor_id' => $monitor->id,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTime,
        ]);
    }

    protected function check(Monitor $monitor): array
    {
        $startTime = microtime(true);
        $statusCode = null;
        $isUp = false;

        try {
            $response = Http::timeout($this->timeoutSeconds)->get($monitor->url);
            $statusCode = $response->status();
            $isUp = $response->successful();

            if ($isUp && $monitor->keyword) {
                $isUp = !str_contains($response->body(), $monitor->keyword);
            }
        } catch (ConnectionException $e) {
            $statusCode = null;
            $isUp = false;
            $this->logNetworkException($monitor, $e);
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->response->status() : null;
            $isUp = false;
            $this->logNetworkException($monitor, $e);
        } catch (\Throwable $th) {
            $statusCode = null;
            $isUp = false;
            $this->logNetworkException($monitor, $th);
        }

        $responseTime = (int) round((microtime(true) - $startTime) * 1000);

        return [$isUp, $statusCode, $responseTime];
    }

    protected function logNetworkException(Monitor $monitor, \Throwable $e): void
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'could not resolve host') || str_contains($message, 'name or service not known') || str_contains($message, 'dns')) {
            $errorType = 'dns_resolution_failure';
            $logMessage = "DNS resolution failure for monitor [{$monitor->id}] ({$monitor->url})";
        } elseif (str_contains($message, 'ssl') || str_contains($message, 'certificate') || str_contains($message, 'handshake')) {
            $errorType = 'ssl_certificate_failure';
            $logMessage = "SSL certificate verification failure for monitor [{$monitor->id}] ({$monitor->url})";
        } elseif (str_contains($message, 'timed out') || str_contains($message, 'timeout') || str_contains($message, 'curl error 28')) {
            $errorType = 'connection_timeout';
            $logMessage = "HTTP connection timeout for monitor [{$monitor->id}] ({$monitor->url})";
        } else {
            $errorType = 'connection_failure';
            $logMessage = "HTTP transport failure for monitor [{$monitor->id}] ({$monitor->url})";
        }

        Log::warning($logMessage, [
            'monitor_id' => $monitor->id,
            'url' => $monitor->url,
            'error_type' => $errorType,
            'exception_class' => get_class($e),
            'error_message' => $e->getMessage(),
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
        } catch (ConnectionException $e) {
            Log::warning("Discord webhook connection timeout/failure", [
                'error_type' => 'webhook_connection_timeout',
                'error_message' => $e->getMessage(),
            ]);
        } catch (RequestException $e) {
            Log::warning("Discord webhook request failure", [
                'error_type' => 'webhook_request_failure',
                'error_message' => $e->getMessage(),
            ]);
        } catch (\Throwable $th) {
            report($th);
        }
    }
}
