<?php

return [
    'http' => [
        /*
         * Inbound identifiers are accepted only when this switch is enabled,
         * the immediate peer is a trusted proxy, and the value is valid.
         */
        'trust_inbound_ids' => env('OBSERVABILITY_TRUST_INBOUND_IDS', false),
        'request_id_header' => env('OBSERVABILITY_REQUEST_ID_HEADER', 'X-Request-ID'),
        'correlation_id_header' => env('OBSERVABILITY_CORRELATION_ID_HEADER', 'X-Correlation-ID'),
    ],

    'telemetry' => [
        /*
         * Telemetry is deliberately optional. The application remains fully
         * functional when this is disabled or the configured collector is down.
         */
        'enabled' => env('OBSERVABILITY_TELEMETRY_ENABLED', false)
            && ! env('OTEL_SDK_DISABLED', false),
        'service_name' => env('OTEL_SERVICE_NAME', 'youyaku-laravel'),
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
        'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/json'),
        'headers' => env('OTEL_EXPORTER_OTLP_HEADERS', ''),
        'timeout_ms' => (int) env('OTEL_EXPORTER_OTLP_TIMEOUT', 50),
        'resource_attributes' => env(
            'OTEL_RESOURCE_ATTRIBUTES',
            'deployment.environment='.env('APP_ENV', 'production')
        ),
    ],
];
