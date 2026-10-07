<?php

declare(strict_types=1);
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Tests\TestCase;

/*
 * Both suites boot the application.
 *
 * Unit tests here still touch config (encryption keys, settings defaults), so a
 * bare PHPUnit TestCase would fail on container resolution. The cost is a few
 * milliseconds per test; the benefit is that a test never fails for a reason
 * that has nothing to do with what it is testing.
 */
pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Writes a real JPEG of the given size and colour and returns its path.
 *
 * Shared because several suites need genuine image bytes — a fixture file would
 * fix the dimensions, and the pipeline's behaviour depends on them.
 *
 * @param  array{0: int, 1: int, 2: int}  $rgb
 */
function artwork(int $width, int $height, array $rgb = [180, 140, 90]): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));

    $path = sys_get_temp_dir().'/'.uniqid('art_', true).'.jpg';
    imagejpeg($image, $path, 92);
    imagedestroy($image);

    return $path;
}

/** An order awaiting payment, for the given total in cents. */
function pendingOrder(int $cents = 1438): Order
{
    $customer = Customer::forEmail('buyer@example.com');

    return Order::create([
        'number' => Order::nextNumber(),
        'customer_id' => $customer->getKey(),
        'status' => OrderStatus::Pending,
        'currency' => 'USD',
        'subtotal_cents' => $cents,
        'total_cents' => $cents,
        'email_hash' => $customer->email_hash,
        'placed_at' => now(),
        'metadata' => ['paypal_order_id' => '5O190127TN364715T'],
    ]);
}
