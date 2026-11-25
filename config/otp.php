<?php

return [

    'digits' => (int) env('OTP_DIGITS', 6),

    'expire_minutes' => (int) env('OTP_EXPIRE_MINUTES', 10),

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),

    'max_verify_attempts' => (int) env('OTP_MAX_VERIFY_ATTEMPTS', 5),

    'max_sends_per_email' => (int) env('OTP_MAX_SENDS_PER_EMAIL', 5),

    'max_sends_per_ip' => (int) env('OTP_MAX_SENDS_PER_IP', 10),

    'max_verifies_per_email' => (int) env('OTP_MAX_VERIFIES_PER_EMAIL', 10),

    'max_verifies_per_ip' => (int) env('OTP_MAX_VERIFIES_PER_IP', 20),

    'verify_decay_seconds' => (int) env('OTP_VERIFY_DECAY_SECONDS', 300),

    'verified_session_minutes' => (int) env('OTP_VERIFIED_SESSION_MINUTES', 30),

    // Queue connection for the registration code email; null uses the default
    // queue. Hosts with no queue worker (shared hosting) set this to "sync" so
    // the code is sent during the request instead of waiting in the jobs table.
    'queue_connection' => env('OTP_QUEUE_CONNECTION'),

];
