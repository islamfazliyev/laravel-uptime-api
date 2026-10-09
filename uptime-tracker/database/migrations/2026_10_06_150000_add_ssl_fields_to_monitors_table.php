<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('certificate_check_enabled')->default(true)->after('keyword');
            $table->timestamp('certificate_expiration_date')->nullable()->after('certificate_check_enabled');
            $table->string('certificate_status')->nullable()->after('certificate_expiration_date');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['certificate_check_enabled', 'certificate_expiration_date', 'certificate_status']);
        });
    }
};
