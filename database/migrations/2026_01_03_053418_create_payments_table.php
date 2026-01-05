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
        Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->cascadeOnDelete();

        // Payment Method
        $table->string('method')->default('bakong');

        // Our reference
        $table->string('invoice_no')->unique();

        // Bakong
        $table->string('bakong_txn_id')->nullable()->index();
        $table->longText('qr_string')->nullable();

        // Amount
        $table->decimal('amount', 10, 2);
        $table->string('currency', 3)->default('USD');

        // Status
        $table->enum('status', [
            'pending',   // QR generated
            'paid',      // success
            'failed',
            'expired',
            'refunded'
        ])->default('pending');

        $table->timestamp('paid_at')->nullable();
        $table->timestamps();

        $table->index('method');
        $table->index('status');
    });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
