<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

class BakongPaymentController extends Controller
{
    /**
     * Create Payment + Generate KHQR for an order
     */
    public function create(Order $order)
    {
        try {
            if ($order->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order នេះបានបង់ប្រាក់រួចហើយ',
                ], 400);
            }

            DB::beginTransaction();

            // Create or reuse pending payment
            $payment = Payment::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'method'   => 'bakong',
                    'status'   => 'pending',
                ],
                [
                    'invoice_no' => 'INV-' . now()->format('YmdHis') . '-' . $order->id,
                    'amount'     => $order->total_price,
                    'currency'   => config('bakong.default_currency', 'USD'),
                ]
            );

            // If QR already exists, return it immediately
            if ($payment->qr_string) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'payment' => $payment,
                    'qr'      => $payment->qr_string,
                    'md5'     => $payment->bakong_txn_id,
                    'message' => 'ប្រើ QR code ដែលមានស្រាប់',
                ]);
            }

            // Generate KHQR directly
            $merchant = new IndividualInfo(
                bakongAccountID: env('BAKONG_ACCOUNT'),
                merchantName: 'VANTHIV HOK',
                merchantCity: 'Phnom Penh',
                currency: KHQRData::CURRENCY_KHR,
                amount: $payment->amount
            );

            $bakong = new BakongKHQR(env('BAKONG_TOKEN'), [
                'guzzle_options' => ['verify' => false]
            ]);

            $qrResponse = $bakong->generateIndividual($merchant);

            if (!($qrResponse->data['qr'] ?? null)) {
                $payment->update(['status' => 'failed']);
                DB::commit();
                return response()->json([
                    'success' => false,
                    'message' => 'មិនអាចបង្កើត KHQR បានទេ',
                ], 500);
            }

            // Save QR and MD5
            $payment->update([
                'bakong_txn_id' => $qrResponse->data['md5'] ?? null,
                'qr_string'     => $qrResponse->data['qr'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'payment' => $payment,
                'qr'      => $payment->qr_string,
                'md5'     => $payment->bakong_txn_id,
                'message' => 'KHQR generated successfully',
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('BakongPayment Create Error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'មានបញ្ហាក្នុងការបង្កើត Payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check / poll payment status by payment ID
     */
    public function check(Payment $payment)
    {
        try {
            $bakong = new BakongKHQR(env('BAKONG_TOKEN'), [
                'guzzle_options' => ['verify' => false]
            ]);

            $result = $bakong->checkTransactionByMD5($payment->bakong_txn_id);

            // Update local payment status if success
            if (($result['responseCode'] ?? 1) === 0) {
                $payment->update(['status' => 'paid']);
                $payment->order->markAsPaid();
            }

            return response()->json([
                'success'      => ($result['responseCode'] ?? 1) === 0,
                'responseCode' => $result['responseCode'] ?? null,
                'message'      => $result['responseMessage'] ?? 'No message',
                'payment'      => $payment,
                'data'         => $result,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
                    'success' => false,
                    'message' => 'មិនអាចលុបចោល Payment ដែលបានបង់ប្រាក់រួចហើយ',
                ], 400);
            }

            $payment->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Payment បានលុបចោលរួចរាល់',
                'payment' => $payment,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
