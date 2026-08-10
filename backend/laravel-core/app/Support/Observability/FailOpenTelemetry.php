<?php

namespace App\Support\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FailOpenTelemetry
{
    private static int $lastFailureLogAt = 0;

    public function __construct(private readonly TelemetryExporter $exporter) {}

    public function exportHttpRequest(
        Request $request,
        ?Response $response,
        float $startedAt,
        float $finishedAt,
    ): void {
        if (! config('observability.telemetry.enabled', false)) {
            return;
        }

        try {
            $this->exporter->exportHttpServerSpan($request, $response, $startedAt, $finishedAt);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);
        }
    }

    private function reportFailure(Throwable $exception): void
    {
        $now = time();

        if ($now - self::$lastFailureLogAt < 60) {
            return;
        }

        self::$lastFailureLogAt = $now;

        try {
            Log::notice('Optional telemetry export failed; application request was preserved.', [
                'exception' => $exception::class,
            ]);
        } catch (Throwable) {
            // Telemetry diagnostics must remain fail-open even if logging fails.
        }
    }
}
