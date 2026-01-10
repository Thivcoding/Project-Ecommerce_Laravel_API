<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'order_number',
        'total_price',
        'status',
        'payment_status',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];

    /* ======================
       Relationships
    ====================== */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /* ======================
       Business Logic
    ====================== */

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');

        $lastOrder = self::whereDate('created_at', today())
            ->latest('id')
            ->first();

        $number = $lastOrder
            ? intval(substr($lastOrder->order_number, -4)) + 1
            : 1;

        return 'ORD-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mark order as paid (after successful payment)
     */
    public function markAsPaid()
    {
        $this->update([
            'payment_status' => 'paid',
            'status' => 'processing', // you can adjust based on your workflow
        ]);
    }

    /**
     * Mark order as cancelled (optional, for cancelled payment)
     */
    public function markAsCancelled()
    {
        $this->update([
            'payment_status' => 'cancelled',
            'status' => 'cancelled',
        ]);
    }

    /* ======================
       Scopes
    ====================== */

    public function scopePending($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }
}
