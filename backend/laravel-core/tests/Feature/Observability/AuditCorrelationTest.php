<?php

namespace Tests\Feature\Observability;

use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditCorrelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_identifiers_are_persisted_on_the_audit_record(): void
    {
        config(['observability.http.trust_inbound_ids' => true]);

        Route::middleware('api')->post('/_test/observability/audit', function (RecordAuditEvent $audit) {
            $audit->execute('test.observability', 'test-resource', '42');

            return response()->json(['ok' => true]);
        });

        $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Request-ID' => 'gateway-request-42',
                'X-Correlation-ID' => 'checkout-flow-7',
            ])
            ->postJson('/_test/observability/audit')
            ->assertOk();

        $audit = AuditLog::query()->where('action', 'test.observability')->firstOrFail();

        $this->assertSame('gateway-request-42', $audit->request_id);
        $this->assertSame('checkout-flow-7', $audit->correlation_id);
    }
}
