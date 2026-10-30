<?php

return [
    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'heuristic'),
        'api_key' => env('OCR_API_KEY'),
        'tesseract' => env('TESSERACT_PATH'),
        'languages' => env('OCR_LANGUAGES', 'spa'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/').'/auth/google/callback'),
    ],
];
