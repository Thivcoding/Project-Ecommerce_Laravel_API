<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;

class BakongService
{
    protected function client()
    {
        return Http::withHeaders([
            'x-api-key'    => config('bakong.api_key'),
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)           // បង្កើន timeout ទៅ 30 វិនាទី
        ->connectTimeout(10);   // Connection timeout 10 វិនាទី
    }

    public function generateKHQR(Payment $payment): array
    {
        try {
            $url = config('bakong.base_url') . '/khqr/generate';
            
            $payload = [
                'merchantId'  => config('bakong.merchant_id'),
                'amount'      => (float) $payment->amount,
                'currency'    => $payment->currency ?? 'USD',
                'billNumber'  => $payment->invoice_no,
                'description' => 'Order #' . $payment->order_id,
                'callbackUrl' => config('bakong.callback_url'),
            ];

            // Log request
            Log::info('Bakong KHQR Generate Request', [
                'url' => $url,
                'payload' => $payload
            ]);

            $response = $this->client()
                ->retry(3, 1000)  // ព្យាយាម 3 ដង រង់ចាំ 1 វិនាទី
                ->post($url, $payload);

            // Log response
            Log::info('Bakong KHQR Generate Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->failed()) {
                $error = $response->json() ?? $response->body();
                Log::error('Bakong KHQR Generate Failed', ['error' => $error]);
                return ['error' => $error];
            }

            $json = $response->json();

            if (!isset($json['transactionId'])) {
                Log::error('Bakong KHQR Missing transactionId', ['response' => $json]);
                return ['error' => 'transactionId missing'];
            }

            return [
                'transactionId' => $json['transactionId'],
                'qrString'      => $json['qrString'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // កំហុស Connection ជាក់លាក់
            Log::error('Bakong Connection Error', [
                'message' => $e->getMessage(),
                'url' => config('bakong.base_url')
            ]);
            return ['error' => 'មិនអាចភ្ជាប់ទៅ Bakong API។ សូមពិនិត្យ network connectivity។'];
            
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // កំហុស HTTP Request
            Log::error('Bakong Request Error', [
                'message' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : null
            ]);
            return ['error' => $e->getMessage()];
            
        } catch (\Throwable $e) {
            // កំហុសផ្សេងៗ
            Log::error('Bakong KHQR Generate Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function checkStatus(string $transactionId): array
    {
        try {
            $url = config('bakong.base_url') . "/payment/status/{$transactionId}";
            
            // Log request
            Log::info('Bakong Status Check Request', [
                'url' => $url,
                'transactionId' => $transactionId
            ]);

            $response = $this->client()
                ->retry(3, 1000)
                ->get($url);

            // Log response
            Log::info('Bakong Status Check Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->failed()) {
                $error = $response->json() ?? $response->body();
                Log::error('Bakong Status Check Failed', ['error' => $error]);
                return ['error' => $error];
            }

            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Bakong Status Check Connection Error', [
                'message' => $e->getMessage(),
                'transactionId' => $transactionId
            ]);
            return ['error' => 'មិនអាចភ្ជាប់ទៅ Bakong API។'];
            
        } catch (\Throwable $e) {
            Log::error('Bakong Status Check Exception', [
                'message' => $e->getMessage(),
                'transactionId' => $transactionId
            ]);
            return ['error' => $e->getMessage()];
        }
    }
}