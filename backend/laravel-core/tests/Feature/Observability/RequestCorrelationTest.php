<?php

namespace Tests\Feature\Observability;

use App\Support\Observability\ObservabilityContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RequestCorrelationTest extends TestCase
{
    public function test_cors_contract_exposes_generated_identifiers(): void
    {
        $this->assertContains('X-Request-ID', config('cors.exposed_headers'));
        $this->assertContains('X-Correlation-ID', config('cors.exposed_headers'));
    }

    public function test_disabled_inbound_identifier_trust_replaces_even_valid_proxy_headers(): void
    {
        config(['observability.http.trust_inbound_ids' => false]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Request-ID' => 'forwarded-but-not-overwritten',
                'X-Correlation-ID' => 'forwarded-correlation',
            ])
            ->getJson('/api/health')
            ->assertOk();

        $requestId = (string) $response->headers->get('X-Request-ID');

        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, $response->headers->get('X-Correlation-ID'));
        $this->assertNotSame('forwarded-but-not-overwritten', $requestId);
        $this->assertNotSame('forwarded-correlation', $response->headers->get('X-Correlation-ID'));
    }

    public function test_trusted_valid_identifiers_are_propagated_to_response(): void
    {
        config(['observability.http.trust_inbound_ids' => true]);

        Route::middleware('api')->get('/_test/observability/context', function () {
            return response()->json(app(ObservabilityContext::class)->all());
        });

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Request-ID' => 'gateway-request-42',
                'X-Correlation-ID' => 'checkout-flow-7',
            ])
            ->getJson('/_test/observability/context')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'gateway-request-42')
            ->assertHeader('X-Correlation-ID', 'checkout-flow-7')
            ->assertJsonPath('request_id', 'gateway-request-42')
            ->assertJsonPath('correlation_id', 'checkout-flow-7');

        $this->assertSame('gateway-request-42', $response->headers->get('X-Request-ID'));
        $this->assertSame([], app(ObservabilityContext::class)->all());
        $this->assertFalse(Context::has('request_id'));
        $this->assertFalse(Context::has('correlation_id'));
    }

    public function test_untrusted_spoofed_identifiers_are_replaced_with_one_generated_uuid(): void
    {
        config(['observability.http.trust_inbound_ids' => true]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->withHeaders([
                'X-Request-ID' => 'attacker-controlled-request',
                'X-Correlation-ID' => 'attacker-controlled-correlation',
            ])
            ->getJson('/api/health')
            ->assertOk();

        $requestId = (string) $response->headers->get('X-Request-ID');
        $correlationId = (string) $response->headers->get('X-Correlation-ID');

        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, $correlationId);
        $this->assertNotSame('attacker-controlled-request', $requestId);
        $this->assertNotSame('attacker-controlled-correlation', $correlationId);
    }

    public function test_malformed_identifiers_from_a_trusted_proxy_are_replaced(): void
    {
        config(['observability.http.trust_inbound_ids' => true]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Request-ID' => str_repeat('x', 65),
                'X-Correlation-ID' => '../bad correlation',
            ])
            ->getJson('/api/health')
            ->assertOk();

        $requestId = (string) $response->headers->get('X-Request-ID');

        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, $response->headers->get('X-Correlation-ID'));
    }

    public function test_rendered_server_error_keeps_safe_identifiers_without_exposing_exception(): void
    {
        config([
            'app.debug' => false,
            'observability.http.trust_inbound_ids' => true,
        ]);

        Route::middleware('api')->get('/_test/observability/failure', static function (): never {
            throw new RuntimeException('private exception material');
        });

        $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Request-ID' => 'gateway-error-request',
                'X-Correlation-ID' => 'gateway-error-correlation',
            ])
            ->getJson('/_test/observability/failure')
            ->assertStatus(500)
            ->assertHeader('X-Request-ID', 'gateway-error-request')
            ->assertHeader('X-Correlation-ID', 'gateway-error-correlation')
            ->assertDontSee('private exception material');

        $this->assertSame([], app(ObservabilityContext::class)->all());
        $this->assertFalse(Context::has('request_id'));
        $this->assertFalse(Context::has('correlation_id'));
    }
}
