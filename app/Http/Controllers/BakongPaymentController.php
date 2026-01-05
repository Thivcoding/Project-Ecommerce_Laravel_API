<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\BakongService;
use Illuminate\Http\Request;

class BakongPaymentController extends Controller
{
    protected $bakong;

    public function __construct(BakongService $bakong)
    {
        $this->bakong = $bakong;
    }

    /**
     * Create Payment + Generate KHQR
     */
    public function create(Order $order)
    {
        // Create payment record
        $payment = Payment::create([
            'order_id'   => $order->id,
            'method'     => 'bakong',
            'invoice_no' => 'INV-' . now()->format('YmdHis') . '-' . $order->id,
            'amount'     => $order->total_price,
            'currency'   => config('bakong.default_currency'),
            'status'     => 'pending',
        ]);

        //  Generate KHQR using BakongService
        $response = $this->bakong->generateKHQR($payment);

        //  Handle error if KHQR failed
        if (isset($response['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate KHQR',
                'details' => $response['error'],
            ], 500);
        }

        // Update payment with transaction ID and QR string
        $payment->update([
            'bakong_txn_id' => $response['transactionId'] ?? null,
            'qr_string'     => $response['qrString'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'payment_id' => $payment->id,
            'qr_string' => $payment->qr_string,
        ]);
    }

    /**
     * Callback from Bakong
     */
    public function callback(Request $request)
    {
        $payment = Payment::where('bakong_txn_id', $request->transactionId)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $status = strtoupper($request->status);

        if ($status === 'SUCCESS') {
            $payment->markAsPaid();
        } elseif ($status === 'FAILED') {
            $payment->markAsFailed();
        }

        return response()->json(['status' => 'success', 'message' => 'Payment status updated']);
    }

    /**
     * Check payment status manually
     */
    public function check(Payment $payment)
    {
        if (!$payment->bakong_txn_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'No Bakong transaction ID'
            ], 400);
        }

        $response = $this->bakong->checkStatus($payment->bakong_txn_id);

        // Handle error from service
        if (isset($response['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check status',
                'details' => $response['error'],
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'payment_status' => $response,
        ]);
    }
}
