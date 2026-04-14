<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            // Who triggered this event
            $table->enum('source', ['ghl', 'lulu', 'system', 'admin'])->default('system');

            // What happened
            $table->string('event_type'); // e.g. 'webhook_received', 'lulu_job_created', 'retry_attempted'

            // Full payload for audit trail
            $table->json('payload')->nullable();

            // Optional note
            $table->text('message')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
