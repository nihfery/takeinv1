<?php

namespace App\Modules\Audit\Application\Actions;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Support\Observability\ObservabilityContext;
use App\Support\Observability\SensitiveDataRedactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordAuditEvent
{
    public function execute(
        string $action,
        string $resourceType,
        string|int|null $resourceId = null,
        array $before = [],
        array $after = [],
        ?Authenticatable $actor = null,
        ?int $providerId = null,
        ?int $branchId = null,
    ): ?AuditLog {
        try {
            $request = app()->bound('request') ? request() : null;
            $actor ??= $request instanceof Request ? $request->user() : null;
            $context = app(ObservabilityContext::class);
            $redactor = app(SensitiveDataRedactor::class);

            return AuditLog::query()->create([
                'actor_type' => $actor ? $actor::class : null,
                'actor_id' => $actor ? (string) $actor->getAuthIdentifier() : null,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId === null ? null : (string) $resourceId,
                'provider_id' => $providerId,
                'branch_id' => $branchId,
                'request_id' => $request instanceof Request
                    ? ($request->attributes->get('request_id') ?? $context->requestId())
                    : $context->requestId(),
                'correlation_id' => $request instanceof Request
                    ? ($request->attributes->get('correlation_id') ?? $context->correlationId())
                    : $context->correlationId(),
                'ip' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request
                    ? mb_substr((string) $request->userAgent(), 0, 2000)
                    : null,
                'before' => $before === [] ? null : $redactor->redact($before),
                'after' => $after === [] ? null : $redactor->redact($after),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to persist audit event.', [
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
