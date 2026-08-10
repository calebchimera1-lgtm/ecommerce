<?php

/**
 * Cron entry point for Module 26 - abandoned cart recovery emails.
 * Not routed through the web front controller: this app has no
 * background scheduler, so a real deployment invokes this file
 * directly via the system crontab (see DEPLOYMENT.md). Safe to run as
 * often as hourly - a cart is only ever emailed once per idle spell
 * (Cart::abandoned()/markReminderSent()), so re-running before the
 * next scheduled tick just finds nothing new to send.
 *
 * Usage: php bin/send-abandoned-cart-emails.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Logger;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Setting;
use App\Services\Notification\Mailer;

date_default_timezone_set((string) config('app.timezone'));

$thresholdHours = (int) Setting::get('abandoned_cart_threshold_hours', 24);
$carts = Cart::abandoned($thresholdHours);

$sent = 0;
$failed = 0;

foreach ($carts as $cart) {
    $items = CartItem::forCart((int) $cart['id']);

    if ($items === []) {
        continue;
    }

    $name = trim($cart['first_name'] . ' ' . $cart['last_name']);

    $delivered = Mailer::send(
        $cart['email'],
        'You left something at Kymera Collection',
        'abandoned-cart',
        ['name' => $name, 'items' => $items, 'cartUrl' => url('/cart')]
    );

    if (!$delivered) {
        $failed++;
        continue;
    }

    Cart::markReminderSent((int) $cart['id']);
    $sent++;
}

$summary = sprintf(
    'Abandoned cart reminders: %d eligible, %d sent, %d failed (threshold %dh).',
    count($carts),
    $sent,
    $failed,
    $thresholdHours
);

Logger::log('info', $summary);
echo $summary . PHP_EOL;
