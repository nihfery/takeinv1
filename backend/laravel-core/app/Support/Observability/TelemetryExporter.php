<?php

namespace App\Support\Observability;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface TelemetryExporter
{
    public function exportHttpServerSpan(
        Request $request,
        ?Response $response,
        float $startedAt,
        float $finishedAt,
    ): void;
}
