<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cashfree Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Cashfree API credentials here.
    | Get your credentials from: https://dashboard.cashfree.com
    |
    */

    'app_id' => env('CASHFREE_APP_ID', ''),

    'secret_key' => env('CASHFREE_SECRET_KEY', ''),

    'sandbox' => env('CASHFREE_SANDBOX', true),

    'api_version' => env('CASHFREE_API_VERSION', '2023-08-01'),

    /*
    |--------------------------------------------------------------------------
    | Return URL
    |--------------------------------------------------------------------------
    |
    | URL where user is redirected after payment.
    | Update this to your actual domain.
    |
    */

    'return_url' => env('CASHFREE_RETURN_URL', 'https://gymxbook.com/open-app'),

];
