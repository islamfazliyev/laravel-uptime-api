<?php

namespace App\Console\Commands;

use App\Models\Ping;
use Illuminate\Console\Command;

class CleanUptime extends Command
{
    protected $signature = 'uptime:clean {--days=7 : Number of days of ping history to retain}';

    protected $description = 'Delete ping records older than the specified retention window.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 7;
        }

        $cutoff = now()->subDays($days);
        $deleted = Ping::where('created_at', '<', $cutoff)->delete();

        $this->info("Successfully pruned {$deleted} ping record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
