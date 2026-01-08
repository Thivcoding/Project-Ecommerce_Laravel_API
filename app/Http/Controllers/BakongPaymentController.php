<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\BakongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        try {
            // ពិនិត្យថា Order មានស្ថានភាពត្រឹមត្រូវ
            if ($order->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order នេះបានបង់ប្រាក់រួចហើយ',
                ], 400);
            }

            // ពិនិត្យថាមាន Payment pending រួចហើយឬនៅ
            $existingPayment = Payment::where('order_id', $order->id)
                ->where('method', 'bakong')
                ->where('status', 'pending')
                ->first();

            if ($existingPayment && $existingPayment->qr_string) {
                // ត្រឡប់ QR code ដែលមានរួចហើយ
                return response()->json([
                    'status' => 'success',
                    'payment_id' => $existingPayment->id,
                    'qr_string' => $existingPayment->qr_string,
                    'message' => 'ប្រើ QR code ដែលមានស្រាប់'
                ]);
            }

            // បង្កើត Payment record ថ្មី
            $payment = Payment::create([
                'order_id'   => $order->id,
                'method'     => 'bakong',
                'invoice_no' => 'INV-' . now()->format('YmdHis') . '-' . $order->id,
                'amount'     => $order->total_price,
                'currency'   => config('bakong.default_currency', 'USD'),
                'status'     => 'pending',
            ]);

            Log::info('Payment Created', ['payment_id' => $payment->id, 'order_id' => $order->id]);

            // Generate KHQR
            $response = $this->bakong->generateKHQR($payment);

            // Handle error
            if (isset($response['error'])) {
                Log::error('KHQR Generation Failed', [
                    'payment_id' => $payment->id,
                    'error' => $response['error']
                ]);

                // កំណត់ Payment status ជា failed
                $payment->update(['status' => 'failed']);

                return response()->json([
                    'status' => 'error',
                    'message' => 'មិនអាចបង្កើត KHQR បានទេ',
                    'details' => $response['error'],
                ], 500);
            }

            // Update payment ជាមួយ transaction ID និង QR string
            $payment->update([
                'bakong_txn_id' => $response['transactionId'] ?? null,
                'qr_string'     => $response['qrString'] ?? null,
            ]);

            Log::info('KHQR Generated Successfully', [
                'payment_id' => $payment->id,
                'bakong_txn_id' => $payment->bakong_txn_id
            ]);

            return response()->json([
                'status' => 'success',
                'payment_id' => $payment->id,
                'qr_string' => $payment->qr_string,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'invoice_no' => $payment->invoice_no,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Creation Error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'មានបញ្ហាក្នុងការបង្កើត Payment',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Callback from Bakong
     */
    public function callback(Request $request)
    {
        try {
            Log::info('Bakong Callback Received', $request->all());

            // Validate request
            $validated = $request->validate([
                'transactionId' => 'required|string',
                'status' => 'required|string',
            ]);

            $payment = Payment::where('bakong_txn_id', $validated['transactionId'])->first();

            if (!$payment) {
                Log::warning('Payment Not Found', ['transactionId' => $validated['transactionId']]);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            $status = strtoupper($validated['status']);

            DB::beginTransaction();
            try {
                if ($status === 'SUCCESS') {
                    // ពិនិត្យថា Payment នេះមិនទាន់ paid
                    if ($payment->status === 'paid') {
                        Log::warning('Payment Already Paid', ['payment_id' => $payment->id]);
                        DB::rollBack();
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Payment was already processed'
                        ]);
                    }

                    $payment->markAsPaid();
                    
                    // Update Order status
                    if ($payment->order) {
                        $payment->order->update(['status' => 'paid']);
                    }

                    Log::info('Payment Marked as Paid', ['payment_id' => $payment->id]);
                    
                } elseif ($status === 'FAILED') {
                    $payment->markAsFailed();
                    Log::info('Payment Marked as Failed', ['payment_id' => $payment->id]);
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment status updated'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Callback Validation Failed', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'Invalid callback data',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Callback Processing Error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'Callback processing failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status manually
     */
    public function check(Payment $payment)
    {
        try {
            if (!$payment->bakong_txn_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'គ្មាន Bakong transaction ID'
                ], 400);
            }

            Log::info('Checking Payment Status', [
                'payment_id' => $payment->id,
                'bakong_txn_id' => $payment->bakong_txn_id
            ]);

            // ពិនិត្យ status ពី Bakong
            $response = $this->bakong->checkStatus($payment->bakong_txn_id);

            // Handle error
            if (isset($response['error'])) {
                Log::error('Status Check Failed', [
                    'payment_id' => $payment->id,
                    'error' => $response['error']
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'មិនអាចពិនិត្យ status បានទេ',
                    'details' => $response['error'],
                ], 500);
            }

            // Update payment status ប្រសិនបើមានការផ្លាស់ប្តូរ
            if (isset($response['status'])) {
                $apiStatus = strtoupper($response['status']);
                
                DB::beginTransaction();
                try {
                    if ($apiStatus === 'SUCCESS' && $payment->status !== 'paid') {
                        $payment->markAsPaid();
                        
                        if ($payment->order) {
                            $payment->order->update(['status' => 'paid']);
                        }
                        
                        Log::info('Payment Status Updated to Paid', ['payment_id' => $payment->id]);
                        
                    } elseif ($apiStatus === 'FAILED' && $payment->status !== 'failed') {
                        $payment->markAsFailed();
                        Log::info('Payment Status Updated to Failed', ['payment_id' => $payment->id]);
                    }
                    
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            // Reload payment ដើម្បីទទួលបាន status ថ្មី
            $payment->refresh();

            return response()->json([
                'status' => 'success',
                'payment' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'invoice_no' => $payment->invoice_no,
                ],
                'bakong_status' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('Status Check Error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'មានបញ្ហាក្នុងការពិនិត្យ status',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel payment
     */
    public function cancel(Payment $payment)
    {
        try {
            if ($payment->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'មិនអាចលុបចោល Payment ដែលបានបង់ប្រាក់រួចហើយ'
                ], 400);
            }

            $payment->update(['status' => 'cancelled']);

            Log::info('Payment Cancelled', ['payment_id' => $payment->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment បានលុបចោលរួចរាល់'
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Cancellation Error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'មិនអាចលុបចោល Payment បានទេ',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}