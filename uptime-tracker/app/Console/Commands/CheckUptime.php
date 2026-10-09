<?php

namespace App\Console\Commands;

use App\Jobs\CheckSingleMonitorJob;
use App\Jobs\CheckSslCertificateJob;
use App\Models\Monitor;
use Illuminate\Console\Command;

class CheckUptime extends Command
{
    protected $signature = 'uptime:check';
    protected $description = 'Süresi gelen monitor\'lar için kontrol job\'ları kuyruğa ekler.';

    public function handle()
    {
        // The scheduler runs every minute. A monitor is due when
        // last_checked_at + check_interval has passed. The 30s tolerance
        // stops a 1-minute monitor from drifting to a 2-minute cycle.
        $due = Monitor::all()->filter(function (Monitor $monitor) {
            if ($monitor->is_paused) return false;
            return $monitor->last_checked_at === null
            || $monitor->last_checked_at->copy()->addSeconds($monitor->check_interval)->isPast();
        });

        if ($due->isEmpty()) {
            $this->info('Kontrol edilecek monitor yok.');
            return;
        }

        foreach ($due as $monitor) {
            CheckSingleMonitorJob::dispatch($monitor);
            if ($monitor->certificate_check_enabled && str_starts_with($monitor->url, 'https://')) {
                CheckSslCertificateJob::dispatch($monitor);
            }
        }

        $this->info("{$due->count()} monitor kontrol için kuyruğa eklendi.");
    }
}
