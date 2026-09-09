<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('lulu_environment')->nullable()->index();
            $table->timestamp('submission_started_at')->nullable();
            $table->string('ghl_synced_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['lulu_environment', 'submission_started_at', 'ghl_synced_status']);
        });
    }
};
