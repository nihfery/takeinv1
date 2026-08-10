<?php

namespace App\Providers;

use App\Support\Observability\FailOpenTelemetry;
use App\Support\Observability\ObservabilityContext;
use App\Support\Observability\OtlpHttpJsonExporter;
use App\Support\Observability\TelemetryExporter;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ObservabilityContext::class);
        $this->app->singleton(TelemetryExporter::class, OtlpHttpJsonExporter::class);
        $this->app->singleton(FailOpenTelemetry::class);
    }

    public function boot(): void
    {
        Queue::createPayloadUsing(static function (): array {
            try {
                $identifiers = app(ObservabilityContext::class)->all();

                return $identifiers === []
                    ? []
                    : ['observability' => $identifiers];
            } catch (Throwable) {
                return [];
            }
        });

        Queue::before(static function (JobProcessing $event): void {
            try {
                app(ObservabilityContext::class)->activateFromQueuePayload(
                    $event->job->payload()['observability'] ?? []
                );
            } catch (Throwable) {
                // Correlation metadata must never make a queued job fail.
            }
        });

        $clearContext = static function (): void {
            try {
                app(ObservabilityContext::class)->clear();
            } catch (Throwable) {
                // Cleanup remains fail-open and is retried before the next job.
            }
        };

        Queue::after($clearContext);
        Queue::exceptionOccurred($clearContext);
        Queue::looping($clearContext);
    }
}
