<?php

use App\Http\Middleware\EnsureProviderAccountActive;
use App\Http\Middleware\EnsureProviderApiAccess;
use App\Http\Middleware\EnsureProviderDocumentVerified;
use App\Http\Middleware\EnsureProviderMenuPermission;
use App\Http\Middleware\EnsureRequestIdentifiers;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RecordHttpTelemetry;
use App\Modules\Media\Console\Commands\MigrateLegacyMedia;
use App\Modules\Subscription\Console\Commands\GrantLegacySubscriptions;
use App\Support\Observability\ObservabilityContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        GrantLegacySubscriptions::class,
        MigrateLegacyMedia::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust only explicitly configured reverse proxies. Production may use
        // "*" only while the backend origin is private to the Compose network.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '127.0.0.1,::1'),
            headers: Request::HEADER_X_FORWARDED_TRAEFIK
        );

        $middleware->statefulApi();

        // Preserve Laravel's default global stack while placing identifiers
        // immediately after trusted-proxy normalization and before middleware
        // that can short-circuit (CORS, maintenance, or post-size validation).
        // Request context wraps telemetry so exporter failures retain the IDs.
        $middleware->use([
            ValidatePathEncoding::class,
            InvokeDeferredCallbacks::class,
            TrustProxies::class,
            EnsureRequestIdentifiers::class,
            RecordHttpTelemetry::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        $middleware->alias([
            'prevent-back-history' => PreventBackHistory::class,
            'provider.account.active' => EnsureProviderAccountActive::class,
            'provider.document.verified' => EnsureProviderDocumentVerified::class,
            'provider.menu' => EnsureProviderMenuPermission::class,
            'provider.api' => EnsureProviderApiAccess::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('provider/*') || $request->is('provider-branch/*')
                ? route('provider.login')
                : route('admin.login');
        });

        $middleware->validateCsrfTokens(except: [
            'provider/signin',
            'api/auth/register/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, mixed $_exception, Request $request): Response {
            try {
                $requestHeader = (string) config('observability.http.request_id_header', 'X-Request-ID');
                $correlationHeader = (string) config(
                    'observability.http.correlation_id_header',
                    'X-Correlation-ID'
                );

                if ($requestId = $request->attributes->get('request_id')) {
                    $response->headers->set($requestHeader, (string) $requestId);
                }

                if ($correlationId = $request->attributes->get('correlation_id')) {
                    $response->headers->set($correlationHeader, (string) $correlationId);
                }

                return $response;
            } finally {
                app(ObservabilityContext::class)->clear();
            }
        });
    })->create();
