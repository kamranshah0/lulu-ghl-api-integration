<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // GHL Identifiers
            $table->string('ghl_contact_id')->nullable()->index();
            $table->string('ghl_order_id')->unique(); // Idempotency key

            // Payment
            $table->enum('payment_status', ['paid', 'unpaid', 'refunded'])->default('paid');

            // Fulfillment pipeline status
            $table->enum('fulfillment_status', [
                'received',
                'processing',
                'submitted_to_lulu',
                'print_job_created',
                'in_production',
                'shipped',
                'cancelled',
                'failed',
            ])->default('received')->index();

            // Lulu job tracking
            $table->string('lulu_job_id')->nullable()->index();
            $table->string('lulu_status')->nullable();

            // Book / Product info
            $table->string('book_sku')->nullable();  // POD Package ID
            $table->integer('quantity')->default(1);

            // Buyer info
            $table->string('buyer_name');
            $table->string('buyer_email')->index();
            $table->string('buyer_phone')->nullable();

            // Shipping address
            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_country')->default('US');

            // Financials
            $table->decimal('amount_charged', 10, 2)->nullable();
            $table->decimal('print_cost_estimate', 10, 2)->nullable();
            $table->decimal('shipping_cost_estimate', 10, 2)->nullable();

            // Error handling
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Raw GHL payload snapshot
            $table->json('raw_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
