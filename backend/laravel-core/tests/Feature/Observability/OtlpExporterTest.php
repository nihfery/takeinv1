<?php

namespace Tests\Feature\Observability;

use App\Support\Observability\OtlpHttpJsonExporter;
use Illuminate\Http\Client\Request as OutboundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OtlpExporterTest extends TestCase
{
    public function test_export_timeout_is_clamped_to_a_fail_open_range(): void
    {
        $this->assertSame(20, OtlpHttpJsonExporter::boundedTimeoutMilliseconds(1));
        $this->assertSame(50, OtlpHttpJsonExporter::boundedTimeoutMilliseconds(50));
        $this->assertSame(250, OtlpHttpJsonExporter::boundedTimeoutMilliseconds(50_000));
    }

    public function test_export_is_one_bounded_otlp_json_attempt_without_request_pii(): void
    {
        config([
            'observability.telemetry.endpoint' => 'http://collector.internal:4318',
            'observability.telemetry.protocol' => 'http/json',
            'observability.telemetry.headers' => 'Authorization=Bearer%20collector-secret',
            'observability.telemetry.timeout_ms' => 50,
            'observability.telemetry.service_name' => 'youyaku-test',
            'observability.telemetry.resource_attributes' => 'deployment.environment=test',
        ]);
        Http::fake([
            'http://collector.internal:4318/v1/traces' => Http::response([], 202),
        ]);

        $request = Request::create(
            '/api/bookings/123?email=private@example.test',
            'POST',
            [
                'password' => 'private-password',
                'phone' => '081234567890',
            ],
        );
        $request->attributes->set('request_id', 'request-safe-1');
        $request->attributes->set('correlation_id', 'correlation-safe-1');

        app(OtlpHttpJsonExporter::class)->exportHttpServerSpan(
            $request,
            new Response('', 201),
            1_786_354_000.100,
            1_786_354_000.125,
        );

        Http::assertSentCount(1);
        Http::assertSent(function (OutboundRequest $outbound): bool {
            $body = $outbound->body();

            $this->assertSame('http://collector.internal:4318/v1/traces', $outbound->url());
            $this->assertTrue($outbound->hasHeader('Authorization', 'Bearer collector-secret'));
            $this->assertStringContainsString('request-safe-1', $body);
            $this->assertStringContainsString('correlation-safe-1', $body);
            $this->assertStringContainsString('unmatched', $body);
            $this->assertStringNotContainsString('private@example.test', $body);
            $this->assertStringNotContainsString('private-password', $body);
            $this->assertStringNotContainsString('081234567890', $body);
            $this->assertStringNotContainsString('collector-secret', $body);

            return true;
        });

        $this->assertLessThanOrEqual(50, config('observability.telemetry.timeout_ms'));
    }
}
