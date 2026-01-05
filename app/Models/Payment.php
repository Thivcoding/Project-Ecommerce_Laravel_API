<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',

        // payment info
        'method',
        'invoice_no',

        // bakong
        'bakong_txn_id',
        'qr_string',

        // money
        'amount',
        'currency',

        // status
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at'=> 'datetime',
    ];

    /* ======================
        Relationships
    ====================== */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /* ======================
        Helpers (Recommended)
    ====================== */

    public function markAsPaid()
    {
        $this->update([
            'status'  => 'paid',
            'paid_at'=> now(),
        ]);

        $this->order->markAsPaid();
    }

    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed',
        ]);
    }
}
