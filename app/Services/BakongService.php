<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BakongService
{
    protected function client()
    {
        return Http::withHeaders([
            'x-api-key'    => config('bakong.api_key'),
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Generate Dynamic KHQR with timeout and retry
     */
    public function generateKHQR($payment): array
    {
        try {
            $response = $this->client()
                ->timeout(60)        // wait up to 60s
                ->retry(3, 1000)     // retry 3 times, 1s interval
                ->withoutVerifying() // ignore SSL for sandbox
                ->post(
                    config('bakong.base_url') . '/khqr/generate',
                    [
                        'merchantId'  => config('bakong.merchant_id'),
                        'amount'      => $payment->amount,
                        'currency'    => $payment->currency ?? 'USD',
                        'billNumber'  => 'INV-' . $payment->id,
                        'description' => 'Order Payment #' . $payment->id,
                        'callbackUrl' => route('bakong.callback'),
                    ]
                );

            if ($response->failed()) {
                return ['error' => $response->body()];
            }

            $json = $response->json();

            // Check required fields
            if (!isset($json['transactionId'])) {
                return ['error' => 'transactionId not returned'];
            }

            return [
                'transactionId' => $json['transactionId'],
                'qrString'      => $json['qrString'] ?? null,
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check payment status with retry
     */
    public function checkStatus(string $transactionId): array
    {
        try {
            $response = $this->client()
                ->timeout(60)
                ->retry(3, 1000)
                ->withoutVerifying()
                ->get(config('bakong.base_url') . "/payment/status/{$transactionId}");

            if ($response->failed()) {
                return ['error' => $response->body()];
            }

            return $response->json();

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
