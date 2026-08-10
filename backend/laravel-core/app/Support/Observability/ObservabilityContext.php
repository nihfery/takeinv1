<?php

namespace App\Support\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ObservabilityContext
{
    private ?string $requestId = null;

    private ?string $correlationId = null;

    public function activate(string $requestId, string $correlationId): void
    {
        $this->requestId = RequestIdentifier::normalize($requestId);
        $this->correlationId = RequestIdentifier::normalize($correlationId);

        if ($this->requestId === null || $this->correlationId === null) {
            $this->requestId = null;
            $this->correlationId = null;

            return;
        }

        try {
            Context::add($this->all());
            Log::withContext($this->all());
        } catch (Throwable) {
            // Logging context is operational metadata and must remain fail-open.
        }
    }

    public function activateFromQueuePayload(array $payload): void
    {
        $this->clear();

        $requestId = RequestIdentifier::normalize($payload['request_id'] ?? null);
        $correlationId = RequestIdentifier::normalize($payload['correlation_id'] ?? null);

        if ($requestId !== null && $correlationId !== null) {
            $this->activate($requestId, $correlationId);
        }
    }

    /**
     * @return array{request_id: string, correlation_id: string}|array{}
     */
    public function all(): array
    {
        if ($this->requestId === null || $this->correlationId === null) {
            return [];
        }

        return [
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
        ];
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    public function clear(): void
    {
        $this->requestId = null;
        $this->correlationId = null;

        try {
            Context::forget(['request_id', 'correlation_id']);
            Log::withoutContext(['request_id', 'correlation_id']);
        } catch (Throwable) {
            // Context cleanup must not make a request or worker fail.
        }
    }
}
