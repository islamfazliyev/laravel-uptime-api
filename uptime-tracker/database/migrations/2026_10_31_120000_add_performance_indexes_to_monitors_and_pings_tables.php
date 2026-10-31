<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->index(['user_id', 'last_checked_at']);
            $table->index('status');
            $table->index('is_paused');
        });

        Schema::table('pings', function (Blueprint $table) {
            $table->index(['monitor_id', 'created_at']);
            $table->index('created_at');
            $table->index('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('pings', function (Blueprint $table) {
            $table->dropIndex(['monitor_id', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status_code']);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_checked_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['is_paused']);
        });
    }
};
