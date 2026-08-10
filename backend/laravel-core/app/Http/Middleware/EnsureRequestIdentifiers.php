<?php

namespace App\Http\Middleware;

use App\Support\Observability\ObservabilityContext;
use App\Support\Observability\RequestIdentifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestIdentifiers
{
    public function __construct(private readonly ObservabilityContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestHeader = (string) config('observability.http.request_id_header', 'X-Request-ID');
        $correlationHeader = (string) config(
            'observability.http.correlation_id_header',
            'X-Correlation-ID'
        );

        $mayTrustInbound = (bool) config('observability.http.trust_inbound_ids', false)
            && $request->isFromTrustedProxy();

        $requestId = $mayTrustInbound
            ? RequestIdentifier::normalize($request->headers->get($requestHeader))
            : null;
        $requestId ??= RequestIdentifier::generate();

        $correlationId = $mayTrustInbound
            ? RequestIdentifier::normalize($request->headers->get($correlationHeader))
            : null;
        $correlationId ??= $requestId;

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('correlation_id', $correlationId);
        $this->context->activate($requestId, $correlationId);

        $response = null;

        try {
            $response = $next($request);
            $response->headers->set($requestHeader, $requestId);
            $response->headers->set($correlationHeader, $correlationId);

            return $response;
        } finally {
            // Keep context active while the exception handler reports/renders.
            // Its response callback performs cleanup for exceptional requests.
            if ($response instanceof Response) {
                $this->context->clear();
            }
        }
    }
}
