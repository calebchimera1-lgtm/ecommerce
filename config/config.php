<?php

declare(strict_types=1);

use App\Core\Env;

Env::load(dirname(__DIR__) . '/.env');

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Kymera Collection'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => (bool) Env::get('APP_DEBUG', false),
        'url' => rtrim((string) Env::get('APP_URL', 'http://localhost'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
        'key' => Env::get('APP_KEY', ''),
    ],

    'mail' => [
        'host' => Env::get('MAIL_HOST'),
        'port' => (int) Env::get('MAIL_PORT', 587),
        'username' => Env::get('MAIL_USERNAME'),
        'password' => Env::get('MAIL_PASSWORD'),
        'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@kymeracollection.com'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'Kymera Collection'),
    ],

    'session' => [
        'name' => Env::get('SESSION_NAME', 'kymera_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
    ],

    'security' => [
        'csrf_token_name' => Env::get('CSRF_TOKEN_NAME', 'kymera_csrf_token'),
        'hash_algo' => constant(Env::get('HASH_ALGO', 'PASSWORD_ARGON2ID')),
        'rate_limit_max_attempts' => (int) Env::get('RATE_LIMIT_MAX_ATTEMPTS', 5),
        'rate_limit_decay_minutes' => (int) Env::get('RATE_LIMIT_DECAY_MINUTES', 15),
    ],

    'payment' => [
        'default_gateway' => Env::get('PAYMENT_DEFAULT_GATEWAY', 'cod'),
        'stripe' => [
            'public_key' => Env::get('STRIPE_PUBLIC_KEY'),
            'secret_key' => Env::get('STRIPE_SECRET_KEY'),
            'webhook_secret' => Env::get('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'client_id' => Env::get('PAYPAL_CLIENT_ID'),
            'client_secret' => Env::get('PAYPAL_CLIENT_SECRET'),
            'mode' => Env::get('PAYPAL_MODE', 'sandbox'),
        ],
        'mpesa' => [
            'env' => Env::get('MPESA_ENV', 'sandbox'),
            'consumer_key' => Env::get('MPESA_CONSUMER_KEY'),
            'consumer_secret' => Env::get('MPESA_CONSUMER_SECRET'),
            'shortcode' => Env::get('MPESA_SHORTCODE'),
            'passkey' => Env::get('MPESA_PASSKEY'),
            'callback_url' => Env::get('MPESA_CALLBACK_URL'),
        ],
    ],

    'uploads' => [
        'max_size_mb' => (int) Env::get('MAX_UPLOAD_SIZE_MB', 5),
        'allowed_image_types' => explode(',', (string) Env::get('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,webp')),
    ],

    'recaptcha' => [
        'site_key' => Env::get('GOOGLE_RECAPTCHA_SITE_KEY'),
        'secret_key' => Env::get('GOOGLE_RECAPTCHA_SECRET_KEY'),
    ],
];
