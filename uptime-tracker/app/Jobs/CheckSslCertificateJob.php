<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Notifications\SslCertificateExpiring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckSslCertificateJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;
    public int $tries = 1;
    public int $uniqueFor = 600;

    public function __construct(public Monitor $monitor)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->monitor->id;
    }

    public function handle(): void
    {
        if (!str_starts_with($this->monitor->url, 'https://') || !$this->monitor->certificate_check_enabled) {
            return;
        }

        $url = parse_url($this->monitor->url);
        $host = $url['host'] ?? null;

        if (!$host) {
            return;
        }

        try {
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $client = stream_socket_client("ssl://{$host}:443", $errorNumber, $errorString, 30, STREAM_CLIENT_CONNECT, $context);

            if ($client) {
                $params = stream_context_get_params($client);
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                
                $expirationDate = \Carbon\Carbon::createFromTimestamp($cert['validTo_time_t']);
                $daysUntilExpiration = now()->diffInDays($expirationDate, false);
                
                $status = 'valid';
                if ($daysUntilExpiration <= 0) {
                    $status = 'expired';
                } elseif ($daysUntilExpiration <= 7) {
                    $status = 'expiring_soon';
                }

                $this->monitor->update([
                    'certificate_expiration_date' => $expirationDate,
                    'certificate_status' => $status,
                ]);

                // Send notification if expiring in exactly 7 days, 3 days, or 1 day to prevent spam
                if (in_array(intval($daysUntilExpiration), [7, 3, 1])) {
                    // Send notification
                    $this->monitor->notify(new SslCertificateExpiring($this->monitor, intval($daysUntilExpiration)));
                }
            }
        } catch (\Exception $e) {
            $this->monitor->update([
                'certificate_status' => 'invalid',
            ]);
            // You could notify about invalid SSL here
        }
    }
}
