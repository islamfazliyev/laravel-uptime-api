<?php

namespace App\Console\Commands;

use App\Jobs\CheckSingleMonitorJob;
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
            return $monitor->last_checked_at === null
                || $monitor->last_checked_at
                    ->copy()
                    ->addMinutes($monitor->check_interval)
                    ->lte(now()->addSeconds(30));
        });

        if ($due->isEmpty()) {
            $this->info('Kontrol edilecek monitor yok.');
            return;
        }

        foreach ($due as $monitor) {
            CheckSingleMonitorJob::dispatch($monitor);
        }

        $this->info("{$due->count()} monitor kontrol için kuyruğa eklendi.");
    }
}
