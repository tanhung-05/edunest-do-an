<?php

return [

    'payment_sim' => [
        'demo' => (bool) env('APP_DEMO', false),
        'secret' => env('PAYMENT_SIM_SECRET'),
    ],

    // P8 M28 (QT-30): bộ mô phỏng ngân hàng cho chuyển khoản. Thông tin tài khoản chỉ để hiện trên QR demo.
    // P10 (CR-255): AI thật. Trống khóa thì dùng bộ giả lập; test luôn dùng bộ giả lập.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        // Thử lần lượt khi mô hình chính quá tải (503, 429) hoặc bị ngừng (404).
        'fallback' => env('GEMINI_FALLBACK_MODELS', 'gemini-flash-lite-latest,gemini-3.8-flash'),
    ],

    // P10 (CR-260): MoMo môi trường thử. Lấy partner code, access key, secret key thử ở developers.momo.vn; trống thì tắt kênh.
    'momo' => [
        'endpoint' => rtrim((string) env('MOMO_ENDPOINT', 'https://test-payment.momo.vn'), '/'),
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
    ],

    // P10 (CR-262): thông báo đẩy trình duyệt. Cặp khóa VAPID sinh trên máy; trống thì tắt.
    'webpush' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:no-reply@edunest.test'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // P10 (CR-258): số hotline hiện trong hộp chat, trang liên hệ.
    'support' => [
        'hotline' => env('SUPPORT_HOTLINE', '0123 456 789'),
    ],

    'bank_sim' => [
        'secret' => env('BANK_SIM_SECRET'),
        'bank' => env('BANK_SIM_BANK', 'Ngân hàng Mô phỏng'),
        'account_number' => env('BANK_SIM_ACCOUNT', '0000000000'),
        'account_name' => env('BANK_SIM_ACCOUNT_NAME', 'EDUNEST DEMO'),
        // CR-261: mã BIN 6 số của ngân hàng nhận (VD 970436 Vietcombank, 970422 MB, 970415 VietinBank, 970418 BIDV, 970407 Techcombank).
        'bin' => env('BANK_BIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
