<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            // Pazartesi - Yavaşlık Tespiti için Sınır
            $table->integer('max_response_time_ms')->default(2000)->after('is_paused');
            // Salı - Public Status Page için Görünürlük
            $table->boolean('is_public')->default(false)->after('max_response_time_ms');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['max_response_time_ms', 'is_public']);
        });
    }
};