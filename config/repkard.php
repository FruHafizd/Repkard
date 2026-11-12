<?php

return [
    // Rahasia penanda tangan token QR kartu (HMAC). Wajib diisi di .env.
    'card_secret' => env('CARD_SECRET'),

    // Ambang "member mulai menghilang": hari tanpa check-in diterima.
    'inactive_warning_days' => (int) env('INACTIVE_WARNING_DAYS', 14),
    'inactive_danger_days' => (int) env('INACTIVE_DANGER_DAYS', 30),
];
