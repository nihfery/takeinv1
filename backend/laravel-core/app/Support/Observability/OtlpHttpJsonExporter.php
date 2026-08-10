<?php

namespace App\Support\Observability;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class OtlpHttpJsonExporter implements TelemetryExporter
{
    public function __construct(private readonly HttpFactory $http) {}

    public function exportHttpServerSpan(
        Request $request,
        ?Response $response,
        float $startedAt,
        float $finishedAt,
    ): void {
        $endpoint = trim((string) config('observability.telemetry.endpoint'));

        if ($endpoint === '') {
            return;
        }

        if (config('observability.telemetry.protocol') !== 'http/json') {
            throw new RuntimeException('Only the OTLP HTTP/JSON protocol is supported.');
        }

        $statusCode = $response?->getStatusCode() ?? 500;
        $requestId = RequestIdentifier::normalize($request->attributes->get('request_id'));
        $correlationId = RequestIdentifier::normalize($request->attributes->get('correlation_id'));
        $route = $request->route();
        $routeTemplate = is_object($route) && method_exists($route, 'uri')
            ? $route->uri()
            : 'unmatched';
        $spanName = $request->method().' '.$routeTemplate;
        $traceSeed = $correlationId ?? $requestId ?? RequestIdentifier::generate();

        $attributes = [
            $this->attribute('http.request.method', $request->method()),
            $this->attribute('http.response.status_code', $statusCode),
            $this->attribute('http.route', $routeTemplate),
        ];

        if ($requestId !== null) {
            $attributes[] = $this->attribute('youyaku.request_id', $requestId);
        }

        if ($correlationId !== null) {
            $attributes[] = $this->attribute('youyaku.correlation_id', $correlationId);
        }

        $payload = [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => $this->resourceAttributes(),
                ],
                'scopeSpans' => [[
                    'scope' => [
                        'name' => 'youyaku.laravel.http',
                        'version' => '1.0.0',
                    ],
                    'spans' => [[
                        'traceId' => substr(hash('sha256', $traceSeed), 0, 32),
                        'spanId' => bin2hex(random_bytes(8)),
                        'name' => $spanName,
                        'kind' => 2,
                        'startTimeUnixNano' => $this->unixNanoseconds($startedAt),
                        'endTimeUnixNano' => $this->unixNanoseconds($finishedAt),
                        'attributes' => $attributes,
                        'status' => [
                            'code' => $statusCode >= 500 ? 2 : 1,
                        ],
                    ]],
                ]],
            ]],
        ];

        // One short attempt only: an optional collector must never become a
        // hidden availability dependency for booking or payment requests.
        $timeout = self::boundedTimeoutMilliseconds(
            config('observability.telemetry.timeout_ms', 50)
        );

        $this->http
            ->withHeaders($this->exportHeaders())
            ->acceptJson()
            ->asJson()
            ->timeout($timeout / 1000)
            ->post(rtrim($endpoint, '/').'/v1/traces', $payload)
            ->throw();
    }

    public static function boundedTimeoutMilliseconds(mixed $configured): int
    {
        return min(250, max(20, (int) $configured));
    }

    /** @return array{key: string, value: array{stringValue?: string, intValue?: string}} */
    private function attribute(string $key, string|int $value): array
    {
        return [
            'key' => $key,
            'value' => is_int($value)
                ? ['intValue' => (string) $value]
                : ['stringValue' => $value],
        ];
    }

    /** @return list<array{key: string, value: array{stringValue: string}}> */
    private function resourceAttributes(): array
    {
        $attributes = [
            $this->attribute(
                'service.name',
                (string) config('observability.telemetry.service_name', 'youyaku-laravel')
            ),
        ];

        foreach ($this->parseKeyValueList(
            (string) config('observability.telemetry.resource_attributes', '')
        ) as $key => $value) {
            $attributes[] = $this->attribute($key, $value);
        }

        return $attributes;
    }

    /** @return array<string, string> */
    private function exportHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            ...$this->parseKeyValueList((string) config('observability.telemetry.headers', '')),
        ];
    }

    /** @return array<string, string> */
    private function parseKeyValueList(string $list): array
    {
        $parsed = [];

        foreach (explode(',', $list) as $entry) {
            if (! str_contains($entry, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $entry, 2));
            $key = rawurldecode($key);
            $value = rawurldecode($value);

            if ($key === ''
                || preg_match('/\A[A-Za-z0-9_.-]+\z/D', $key) !== 1
                || str_contains($value, "\r")
                || str_contains($value, "\n")) {
                continue;
            }

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    private function unixNanoseconds(float $timestamp): string
    {
        return sprintf('%.0f', $timestamp * 1_000_000_000);
    }
}
