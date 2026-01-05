<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bakong API Base URL
    |--------------------------------------------------------------------------
    | Sandbox or Production URL from NBC
    */
    'base_url' => env('BAKONG_BASE_URL', 'https://sandbox-bakong.nbc.gov.kh'),

    /*
    |--------------------------------------------------------------------------
    | Bakong API Key (REQUIRED)
    |--------------------------------------------------------------------------
    | Used for x-api-key header authentication
    */
    'api_key' => env('BAKONG_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Merchant ID (REQUIRED)
    |--------------------------------------------------------------------------
    | Provided by NBC / Bakong
    */
    'merchant_id' => env('BAKONG_MERCHANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('BAKONG_CURRENCY', 'USD'),
    'callback_url'     => env('BAKONG_CALLBACK_URL'),
];
