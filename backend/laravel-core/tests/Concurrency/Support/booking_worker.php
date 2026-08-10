<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

[$script, $payloadPath, $readyPath, $releasePath, $resultPath] = $argv + array_fill(0, 5, null);

if (! is_string($payloadPath)
    || ! is_string($readyPath)
    || ! is_string($releasePath)
    || ! is_string($resultPath)) {
    fwrite(STDERR, "Concurrency worker arguments are incomplete.\n");
    exit(64);
}

try {
    $root = dirname(__DIR__, 3);
    require $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $payload = json_decode(
        (string) file_get_contents($payloadPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (! touch($readyPath)) {
        throw new RuntimeException('Unable to publish the worker readiness marker.');
    }

    $deadline = microtime(true) + 30;
    while (! is_file($releasePath)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out waiting for the concurrency barrier.');
        }

        usleep(10_000);
    }

    $operation = (string) ($payload['operation'] ?? '');
    $arguments = (array) ($payload['arguments'] ?? []);

    $result = match ($operation) {
        'create' => (function () use ($app, $arguments): array {
            $booking = $app->make(BookingFlowService::class)->createBooking(
                (array) ($arguments['payload'] ?? []),
                User::query()->findOrFail((int) $arguments['customer_id'])
            );

            return ['booking_id' => (int) $booking->id, 'status' => (string) $booking->status];
        })(),
        'finalize' => (function () use ($app, $arguments): array {
            $booking = $app->make(BookingFlowService::class)->finalizeHeldBooking(
                Booking::query()->findOrFail((int) $arguments['booking_id']),
                (array) ($arguments['payload'] ?? [])
            );

            return ['booking_id' => (int) $booking->id, 'status' => (string) $booking->status];
        })(),
        'reschedule' => (function () use ($app, $arguments): array {
            $booking = $app->make(BookingFlowService::class)->rescheduleBooking(
                Booking::query()->findOrFail((int) $arguments['booking_id']),
                (array) ($arguments['payload'] ?? [])
            );

            return ['booking_id' => (int) $booking->id, 'status' => (string) $booking->status];
        })(),
        'payment_status' => (function () use ($app, $arguments): array {
            $payment = $app->make(MidtransService::class)->updatePaymentFromStatus(
                Payment::query()->findOrFail((int) $arguments['payment_id']),
                (array) ($arguments['response'] ?? []),
                true,
                (array) ($arguments['response'] ?? [])
            );

            return ['payment_id' => (int) $payment->id, 'status' => (string) $payment->status];
        })(),
        default => throw new RuntimeException("Unsupported concurrency operation [{$operation}]."),
    };

    $output = ['outcome' => 'ok', 'result' => $result];
} catch (ValidationException $exception) {
    $output = [
        'outcome' => 'validation',
        'errors' => $exception->errors(),
    ];
} catch (Throwable $exception) {
    $output = [
        'outcome' => 'error',
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

$encoded = json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
if (file_put_contents($resultPath, $encoded, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the concurrency worker result.\n");
    exit(74);
}

exit(($output['outcome'] ?? 'error') === 'error' ? 1 : 0);
