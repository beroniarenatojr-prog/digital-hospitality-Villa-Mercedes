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

    'verified_session_minutes' => (int) env('OTP_VERIFIED_SESSION_MINUTES', 30),

];
