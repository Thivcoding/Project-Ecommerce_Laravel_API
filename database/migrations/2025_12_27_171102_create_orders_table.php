<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Contact
            $table->string('phone', 20);
            $table->text('address');

            // Order info
            $table->string('order_number')->unique(); // ORD-20250103-0001
            $table->decimal('total_price', 10, 2);

            // Order status (logistics)
            $table->enum('status', [
                'pending',      // order created
                'processing',   // payment confirmed
                'shipped',
                'delivered',
                'cancelled',
                'completed'
            ])->default('pending');

            // Payment summary (ONLY STATUS)
            $table->enum('payment_status', [
                'unpaid',
                'paid',
                'refunded'
            ])->default('unpaid');

            $table->timestamps();

            $table->index('order_number');
            $table->index('status');
            $table->index('payment_status');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
