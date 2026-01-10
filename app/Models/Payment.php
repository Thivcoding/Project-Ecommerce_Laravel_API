<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    // Mass assignable fields
    protected $fillable = [
        'order_id',

        // Payment method info
        'method',
        'invoice_no',

        // Bakong transaction
        'bakong_txn_id',
        'qr_string',

        // Money info
        'amount',
        'currency',

        // Status
        'status',
        'paid_at',
    ];

    // Casts
    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /* ======================
       Relationships
    ====================== */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /* ======================
       Helpers
    ====================== */

    /**
     * Mark payment as paid and update related order
     */
    public function markAsPaid()
    {
        $this->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Safely update order if exists
        if ($this->order) {
            $this->order->markAsPaid();
        }
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Mark payment as cancelled
     */
    public function markAsCancelled()
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }
}
