<?php

namespace Tests\Concurrency;

use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerActivity;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Promotion\Infrastructure\Persistence\Models\Coupon;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    private array $runDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            'mysql',
            DB::connection()->getDriverName(),
            'The mandatory concurrency gate must run against MySQL, not an in-memory substitute.'
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->runDirectories as $directory) {
            if (File::isDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }

        parent::tearDown();
    }

    public function test_many_customers_contending_for_one_capacity_commit_at_most_one_booking(): void
    {
        $date = now()->addDays(5)->toDateString();
        [$branch, $service, $staff] = $this->bookableFixture([$date]);
        $customers = User::factory()->count(6)->create(['role' => 'customer']);

        $jobs = $customers->map(fn (User $customer, int $index): array => [
            'operation' => 'create',
            'arguments' => [
                'customer_id' => $customer->id,
                'payload' => $this->bookingPayload(
                    $branch,
                    $service,
                    $staff,
                    $date,
                    '13:00',
                    "capacity-{$index}"
                ),
            ],
        ])->all();

        $results = $this->runConcurrent($jobs);

        $this->assertCount(1, $this->successfulResults($results));
        $this->assertCount(5, $this->validationResults($results));
        $this->assertSame(1, Booking::query()
            ->where('staff_id', $staff->id)
            ->whereDate('booking_date', $date)
            ->where('start_time', '13:00:00')
            ->whereIn('status', BookingFlowService::ACTIVE_BOOKING_STATUSES)
            ->count());
    }

    public function test_same_idempotency_key_retried_concurrently_returns_one_booking(): void
    {
        $date = now()->addDays(5)->toDateString();
        [$branch, $service, $staff] = $this->bookableFixture([$date]);
        $customer = User::factory()->create(['role' => 'customer']);
        $payload = $this->bookingPayload(
            $branch,
            $service,
            $staff,
            $date,
            '14:00',
            'same-concurrent-key'
        );

        $results = $this->runConcurrent(array_fill(0, 4, [
            'operation' => 'create',
            'arguments' => ['customer_id' => $customer->id, 'payload' => $payload],
        ]));

        $successes = $this->successfulResults($results);
        $this->assertCount(4, $successes);
        $this->assertCount(1, collect($successes)->pluck('result.booking_id')->unique());
        $this->assertSame(1, Booking::query()
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', 'same-concurrent-key')
            ->count());
    }

    public function test_finalize_retries_concurrently_apply_side_effects_once(): void
    {
        $date = now()->addDays(5)->toDateString();
        [$branch, $service, $staff] = $this->bookableFixture([$date]);
        $customer = User::factory()->create(['role' => 'customer']);
        $hold = app(BookingFlowService::class)->createBooking(
            array_merge(
                $this->bookingPayload($branch, $service, $staff, $date, '15:00', 'finalize-concurrent'),
                ['hold_only' => true]
            ),
            $customer
        );

        $jobs = array_fill(0, 4, [
            'operation' => 'finalize',
            'arguments' => [
                'booking_id' => $hold->id,
                'payload' => [
                    'payment_type' => 'pay_at_salon',
                    'participant_count' => 1,
                    'idempotency_key' => 'finalize-concurrent',
                ],
            ],
        ]);

        $results = $this->runConcurrent($jobs);

        $this->assertCount(4, $this->successfulResults($results));
        $this->assertSame('confirmed', $hold->refresh()->status);
        $this->assertNull($hold->hold_expires_at);
        $this->assertSame(1, Payment::query()->where('booking_id', $hold->id)->count());
        $this->assertSame(1, CustomerActivity::query()->where('booking_id', $hold->id)->count());
    }

    public function test_coupon_quota_is_not_oversold_under_parallel_booking_writes(): void
    {
        $date = now()->addDays(5)->toDateString();
        [$branch, $service, $firstStaff, $secondStaff] = $this->bookableFixture([$date], 2);
        $customers = User::factory()->count(2)->create(['role' => 'customer']);
        $coupon = Coupon::query()->create([
            'code' => 'ONLYONE',
            'product_type' => 'all',
            'coupon_type' => 'fixed',
            'coupon_value' => 10000,
            'quantity' => 1,
            'used_count' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        $jobs = [
            [
                'operation' => 'create',
                'arguments' => [
                    'customer_id' => $customers[0]->id,
                    'payload' => array_merge(
                        $this->bookingPayload($branch, $service, $firstStaff, $date, '16:00', 'coupon-a'),
                        ['coupon_code' => $coupon->code]
                    ),
                ],
            ],
            [
                'operation' => 'create',
                'arguments' => [
                    'customer_id' => $customers[1]->id,
                    'payload' => array_merge(
                        $this->bookingPayload($branch, $service, $secondStaff, $date, '16:00', 'coupon-b'),
                        ['coupon_code' => $coupon->code]
                    ),
                ],
            ],
        ];

        $results = $this->runConcurrent($jobs);

        $this->assertCount(1, $this->successfulResults($results));
        $this->assertCount(1, $this->validationResults($results));
        $this->assertSame(1, $coupon->refresh()->used_count);
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(94500.0, (float) Booking::query()->sole()->total_price);
    }

    public function test_parallel_reschedules_cannot_claim_the_same_staff_slot(): void
    {
        $originalDate = now()->addDays(5)->toDateString();
        $targetDate = now()->addDays(6)->toDateString();
        [$branch, $service, $staff] = $this->bookableFixture([$originalDate, $targetDate]);
        $customers = User::factory()->count(2)->create(['role' => 'customer']);
        $bookings = collect([
            $this->existingBooking($branch, $service, $staff, $customers[0], $originalDate, '10:00', 'RES-A'),
            $this->existingBooking($branch, $service, $staff, $customers[1], $originalDate, '12:00', 'RES-B'),
        ]);

        $jobs = $bookings->map(fn (Booking $booking): array => [
            'operation' => 'reschedule',
            'arguments' => [
                'booking_id' => $booking->id,
                'payload' => [
                    'booking_date' => $targetDate,
                    'start_time' => '15:00',
                    'staff_id' => $staff->id,
                ],
            ],
        ])->all();

        $results = $this->runConcurrent($jobs);

        $this->assertCount(1, $this->successfulResults($results));
        $this->assertCount(1, $this->validationResults($results));
        $this->assertSame(1, Booking::query()
            ->where('staff_id', $staff->id)
            ->whereDate('booking_date', $targetDate)
            ->where('start_time', '15:00:00')
            ->whereIn('status', BookingFlowService::ACTIVE_BOOKING_STATUSES)
            ->count());
    }

    public function test_duplicate_payment_notifications_are_serialized_and_replay_safe(): void
    {
        $date = now()->addDays(5)->toDateString();
        [$branch, $service, $staff] = $this->bookableFixture([$date]);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = $this->existingBooking(
            $branch,
            $service,
            $staff,
            $customer,
            $date,
            '17:00',
            'PAY-CONCURRENT',
            'pending_payment'
        );
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'full_payment',
            'amount' => 105000,
            'status' => 'pending',
            'payment_method' => 'midtrans',
        ]);
        $payment->gatewayTransaction()->create([
            'gateway' => 'midtrans',
            'payment_channel' => 'qris',
            'provider_order_id' => 'PAY-CONCURRENT-ORDER',
            'provider_status' => 'pending',
        ]);
        $response = [
            'order_id' => 'PAY-CONCURRENT-ORDER',
            'transaction_id' => 'transaction-concurrent',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '105000.00',
            'currency' => 'IDR',
        ];

        $results = $this->runConcurrent(array_fill(0, 4, [
            'operation' => 'payment_status',
            'arguments' => ['payment_id' => $payment->id, 'response' => $response],
        ]));

        $this->assertCount(4, $this->successfulResults($results));
        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('confirmed', $booking->refresh()->status);
        $this->assertSame(1, CustomerActivity::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('settlement', $payment->gatewayTransaction->provider_status);
    }

    /**
     * @param  list<array{operation:string,arguments:array<string,mixed>}>  $jobs
     * @return list<array<string,mixed>>
     */
    private function runConcurrent(array $jobs): array
    {
        $runDirectory = storage_path('framework/testing/concurrency/'.Str::uuid());
        File::makeDirectory($runDirectory, 0755, true);
        $this->runDirectories[] = $runDirectory;
        $releasePath = $runDirectory.DIRECTORY_SEPARATOR.'release';
        $processes = [];
        $resultPaths = [];
        $readyPaths = [];

        try {
            foreach (array_values($jobs) as $index => $job) {
                $payloadPath = $runDirectory.DIRECTORY_SEPARATOR."payload-{$index}.json";
                $readyPath = $runDirectory.DIRECTORY_SEPARATOR."ready-{$index}";
                $resultPath = $runDirectory.DIRECTORY_SEPARATOR."result-{$index}.json";
                File::put($payloadPath, json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                $process = new Process(
                    [
                        PHP_BINARY,
                        base_path('tests/Concurrency/Support/booking_worker.php'),
                        $payloadPath,
                        $readyPath,
                        $releasePath,
                        $resultPath,
                    ],
                    base_path(),
                    $this->workerEnvironment()
                );
                $process->setTimeout(45);
                $process->start();
                $processes[] = $process;
                $readyPaths[] = $readyPath;
                $resultPaths[] = $resultPath;
            }

            $deadline = microtime(true) + 30;
            while (collect($readyPaths)->contains(fn (string $path): bool => ! File::exists($path))) {
                foreach ($processes as $process) {
                    if (! $process->isRunning() && ! $process->isSuccessful()) {
                        $this->fail('A concurrency worker stopped before the barrier: '.$process->getErrorOutput());
                    }
                }

                if (microtime(true) >= $deadline) {
                    $this->fail('Concurrency workers did not all reach the start barrier within 30 seconds.');
                }

                usleep(20_000);
            }

            File::put($releasePath, 'go');

            foreach ($processes as $index => $process) {
                $exitCode = $process->wait();
                $reportedResult = File::exists($resultPaths[$index])
                    ? File::get($resultPaths[$index])
                    : '<no result file>';
                $this->assertSame(
                    0,
                    $exitCode,
                    "Concurrency worker {$index} failed: {$reportedResult} ".$process->getErrorOutput()
                );
            }

            return collect($resultPaths)
                ->map(function (string $path): array {
                    $this->assertFileExists($path);

                    return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
                })
                ->all();
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }

    /** @return array<string, string|false> */
    private function workerEnvironment(): array
    {
        $connection = config('database.connections.'.config('database.default'));

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_DATABASE' => (string) $connection['database'],
            'DB_HOST' => (string) $connection['host'],
            'DB_PASSWORD' => (string) $connection['password'],
            'DB_PORT' => (string) $connection['port'],
            'DB_URL' => false,
            'DB_USERNAME' => (string) $connection['username'],
            'MAIL_MAILER' => 'array',
            'OTEL_EXPORTER_OTLP_ENDPOINT' => '',
            'PULSE_ENABLED' => 'false',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'TELESCOPE_ENABLED' => 'false',
        ];
    }

    /** @return list<array<string,mixed>> */
    private function successfulResults(array $results): array
    {
        return array_values(array_filter(
            $results,
            fn (array $result): bool => ($result['outcome'] ?? null) === 'ok'
        ));
    }

    /** @return list<array<string,mixed>> */
    private function validationResults(array $results): array
    {
        return array_values(array_filter(
            $results,
            fn (array $result): bool => ($result['outcome'] ?? null) === 'validation'
        ));
    }

    /**
     * @param  list<string>  $dates
     * @return array{ProviderBranch,Service,ProviderStaff,ProviderStaff?}
     */
    private function bookableFixture(array $dates, int $staffCount = 1): array
    {
        $provider = User::factory()->create(['role' => 'provider']);
        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'phone_number' => '08123456789',
            'status' => 'active',
            'document_status' => 'verified',
        ]);
        $workingDays = collect($dates)
            ->map(fn (string $date): string => strtolower(now()->parse($date)->englishDayOfWeek))
            ->unique()
            ->values()
            ->all();
        $branch = ProviderBranch::query()->create([
            'provider_id' => $provider->id,
            'branch_name' => 'Concurrency Salon',
            'email' => 'concurrency-salon@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Concurrency Street',
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => $workingDays,
            'holidays' => [],
            'status' => 'active',
        ]);
        $category = ServiceCategory::query()->create([
            'name' => 'Concurrency Hair',
            'slug' => 'concurrency-hair',
            'description' => 'Concurrency fixture',
            'status' => 'active',
            'is_featured' => false,
        ]);
        $service = Service::query()->create([
            'provider_id' => $provider->id,
            'title' => 'Concurrency Hair Spa',
            'slug' => 'concurrency-hair-spa',
            'category' => $category->name,
            'category_id' => $category->id,
            'code' => 'CONCURRENT',
            'description' => 'Concurrency fixture',
            'includes' => 'Fixture',
            'price_type' => 'fixed',
            'price' => 100000,
            'minimum_duration' => 60,
            'estimated_duration' => 60,
            'maximum_duration' => 60,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'payment_policy' => 'Pay at salon',
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);
        $staff = collect(range(1, $staffCount))->map(function (int $position) use ($provider, $branch, $service, $workingDays): ProviderStaff {
            $staff = ProviderStaff::query()->create([
                'provider_id' => $provider->id,
                'first_name' => "Worker {$position}",
                'last_name' => 'Concurrency',
                'email' => "worker-{$position}@example.test",
                'gender' => 'female',
                'branch_id' => $branch->id,
                'role' => 'Stylist',
                'current_status' => 'available',
                'status' => 'active',
            ]);
            $staff->skills()->attach($service->id);

            foreach ($workingDays as $day) {
                StaffSchedule::query()->create([
                    'staff_id' => $staff->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '21:00',
                    'is_available' => true,
                ]);
            }

            return $staff;
        })->values();

        return [$branch, $service, $staff[0], $staff[1] ?? null];
    }

    /** @return array<string,mixed> */
    private function bookingPayload(
        ProviderBranch $branch,
        Service $service,
        ProviderStaff $staff,
        string $date,
        string $time,
        string $idempotencyKey
    ): array {
        return [
            'branch_id' => $branch->id,
            'service_ids' => [$service->id],
            'booking_type' => 'scheduled',
            'staff_id' => $staff->id,
            'booking_date' => $date,
            'start_time' => $time,
            'payment_type' => 'pay_at_salon',
            'participant_count' => 1,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    private function existingBooking(
        ProviderBranch $branch,
        Service $service,
        ProviderStaff $staff,
        User $customer,
        string $date,
        string $time,
        string $code,
        string $status = 'confirmed'
    ): Booking {
        $booking = Booking::query()->create([
            'booking_code' => $code,
            'booking_date' => $date,
            'provider_id' => $branch->provider_id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'booking_type' => 'scheduled',
            'start_time' => $time,
            'estimated_end_time' => now()->parse("{$date} {$time}")->addHour()->format('H:i'),
            'total_duration' => 60,
            'total_price' => 105000,
            'participant_count' => 1,
            'status' => $status,
        ]);
        $booking->services()->attach($service->id, [
            'price' => 100000,
            'estimated_duration' => 60,
        ]);

        return $booking;
    }
}
