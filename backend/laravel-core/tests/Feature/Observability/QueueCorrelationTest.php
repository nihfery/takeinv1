<?php

namespace Tests\Feature\Observability;

use App\Support\Observability\ObservabilityContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Queue as BaseQueue;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;

class QueueCorrelationTest extends TestCase
{
    public function test_queue_metadata_propagates_identifiers_without_changing_serialized_command(): void
    {
        $context = app(ObservabilityContext::class);
        $queue = Queue::connection('sync');
        $job = new CorrelatedProbeJob('business-payload');
        $createPayload = new ReflectionMethod(BaseQueue::class, 'createPayload');

        $context->clear();
        $withoutContext = json_decode(
            $createPayload->invoke($queue, $job, 'notifications'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $context->activate('request-from-http', 'correlation-from-http');
        $withContextJson = $createPayload->invoke($queue, $job, 'notifications');
        $withContext = json_decode($withContextJson, true, flags: JSON_THROW_ON_ERROR);
        $context->clear();

        $this->assertSame([
            'request_id' => 'request-from-http',
            'correlation_id' => 'correlation-from-http',
        ], $withContext['observability']);
        $this->assertSame(
            $withoutContext['data']['command'],
            $withContext['data']['command'],
            'Correlation metadata must not alter the serialized business command.'
        );

        $restoredJob = unserialize($withContext['data']['command']);
        $this->assertInstanceOf(CorrelatedProbeJob::class, $restoredJob);
        $this->assertSame('business-payload', $restoredJob->businessPayload);

        Log::spy();
        $syncJob = new SyncJob($this->app, $withContextJson, 'sync', 'notifications');
        Event::dispatch(new JobProcessing('sync', $syncJob));

        $this->assertSame('request-from-http', $context->requestId());
        $this->assertSame('correlation-from-http', $context->correlationId());
        $this->assertSame('request-from-http', Context::get('request_id'));
        $this->assertSame('correlation-from-http', Context::get('correlation_id'));
        Log::shouldHaveReceived('withContext')->with([
            'request_id' => 'request-from-http',
            'correlation_id' => 'correlation-from-http',
        ])->once();

        Event::dispatch(new JobProcessed('sync', $syncJob));

        $this->assertSame([], $context->all());
        $this->assertFalse(Context::has('request_id'));
        $this->assertFalse(Context::has('correlation_id'));
        Log::shouldHaveReceived('withoutContext')
            ->with(['request_id', 'correlation_id'])
            ->atLeast()
            ->once();
    }
}

final class CorrelatedProbeJob implements ShouldQueue
{
    public function __construct(public readonly string $businessPayload) {}

    public function handle(): void
    {
        // This test inspects the serialized command without executing it.
    }
}
