<?php

namespace App\Http\Middleware;

use App\Support\Observability\FailOpenTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordHttpTelemetry
{
    public function __construct(private readonly FailOpenTelemetry $telemetry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = null;

        try {
            return $response = $next($request);
        } finally {
            $this->telemetry->exportHttpRequest(
                $request,
                $response,
                $startedAt,
                microtime(true),
            );
        }
    }
}
