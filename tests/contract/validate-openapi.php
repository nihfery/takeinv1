<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;
use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__, 2);
$backend = $root.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'laravel-core';
$contractDirectory = $root.DIRECTORY_SEPARATOR.'contracts'.DIRECTORY_SEPARATOR.'openapi'.DIRECTORY_SEPARATOR.'v1';
$autoload = $backend.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$failures = [];

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

if (! is_file($autoload)) {
    fwrite(STDERR, "Contract validation requires backend Composer dependencies. Run composer install in backend/laravel-core.\n");
    exit(2);
}

require $autoload;

$expectedFiles = [
    'admin.yaml',
    'auth.yaml',
    'customer.yaml',
    'partner.yaml',
    'provider.yaml',
    'public.yaml',
    'webhooks.yaml',
];

$actualFiles = array_map('basename', glob($contractDirectory.DIRECTORY_SEPARATOR.'*.yaml') ?: []);
sort($actualFiles);

if ($actualFiles !== $expectedFiles) {
    $fail(sprintf(
        'Expected exactly these OpenAPI files: %s; found: %s.',
        implode(', ', $expectedFiles),
        implode(', ', $actualFiles),
    ));
}

$documents = [];
$operationIds = [];
$documentedOperations = [];
$httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];

$resolveReference = static function (array $document, string $reference): mixed {
    if (! str_starts_with($reference, '#/')) {
        return null;
    }

    $value = $document;
    foreach (explode('/', substr($reference, 2)) as $segment) {
        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    return $value;
};

$walkReferences = static function (
    mixed $value,
    array $document,
    string $location,
    callable $fail,
) use (&$walkReferences, $resolveReference): void {
    if (! is_array($value)) {
        return;
    }

    if (isset($value['$ref']) && is_string($value['$ref'])) {
        if (! str_starts_with($value['$ref'], '#/')) {
            $fail($location.': external $ref values are intentionally unsupported in this split contract set.');
        } elseif ($resolveReference($document, $value['$ref']) === null) {
            $fail($location.': unresolved local reference '.$value['$ref'].'.');
        }
    }

    foreach ($value as $key => $child) {
        $walkReferences($child, $document, $location.'/'.(string) $key, $fail);
    }
};

$schemaShape = static function (mixed $schema, array $document) use (&$schemaShape, $resolveReference): array {
    if (! is_array($schema)) {
        return ['properties' => [], 'required' => []];
    }

    if (isset($schema['$ref']) && is_string($schema['$ref'])) {
        $resolved = $resolveReference($document, $schema['$ref']);
        if (is_array($resolved)) {
            return $schemaShape($resolved, $document);
        }
    }

    $properties = array_keys(is_array($schema['properties'] ?? null) ? $schema['properties'] : []);
    $required = array_values(array_filter(
        is_array($schema['required'] ?? null) ? $schema['required'] : [],
        'is_string',
    ));

    foreach (is_array($schema['allOf'] ?? null) ? $schema['allOf'] : [] as $part) {
        $shape = $schemaShape($part, $document);
        $properties = array_merge($properties, $shape['properties']);
        $required = array_merge($required, $shape['required']);
    }

    $properties = array_values(array_unique($properties));
    $required = array_values(array_unique($required));
    sort($properties);
    sort($required);

    return ['properties' => $properties, 'required' => $required];
};

foreach ($expectedFiles as $file) {
    $path = $contractDirectory.DIRECTORY_SEPARATOR.$file;

    if (! is_file($path)) {
        continue;
    }

    try {
        $document = Yaml::parseFile($path);
    } catch (Throwable $exception) {
        $fail($file.': YAML parsing failed: '.$exception->getMessage());
        continue;
    }

    if (! is_array($document)) {
        $fail($file.': root must be an object.');
        continue;
    }

    $documents[$file] = $document;

    if (($document['openapi'] ?? null) !== '3.1.0') {
        $fail($file.': openapi must be exactly 3.1.0.');
    }

    if (! is_string($document['info']['title'] ?? null) || trim($document['info']['title']) === '') {
        $fail($file.': info.title is required.');
    }

    if (! is_string($document['info']['version'] ?? null) || trim($document['info']['version']) === '') {
        $fail($file.': info.version is required.');
    }

    $servers = $document['servers'] ?? null;
    if (! is_array($servers) || $servers === []) {
        $fail($file.': at least one production server is required.');
    } else {
        foreach ($servers as $server) {
            $url = is_array($server) ? ($server['url'] ?? null) : null;
            if (! is_string($url) || ! str_starts_with($url, 'https://') || ! str_ends_with($url, '/api')) {
                $fail($file.': server URLs must use HTTPS and end in /api.');
            }
            if (is_string($url) && preg_match('/localhost|127\.0\.0\.1|example\.com/i', $url)) {
                $fail($file.': placeholder or local server URL is not allowed.');
            }
            $expectedHost = $file === 'webhooks.yaml' ? 'hooks.takein.id' : 'api.takein.id';
            if (is_string($url) && parse_url($url, PHP_URL_HOST) !== $expectedHost) {
                $fail($file.': production server host must be '.$expectedHost.'.');
            }
        }
    }

    $paths = $document['paths'] ?? null;
    if (! is_array($paths)) {
        $fail($file.': paths must be an object.');
        continue;
    }

    if ($file === 'partner.yaml') {
        if ($paths !== []) {
            $fail('partner.yaml: partner registrar is empty, so paths must remain empty.');
        }
        if (($document['x-contract-status'] ?? null) !== 'reserved-no-active-routes') {
            $fail('partner.yaml: reserved empty status must be explicit.');
        }
    } elseif ($paths === []) {
        $fail($file.': active API contract cannot have empty paths.');
    }

    foreach ($paths as $apiPath => $pathItem) {
        if (! is_string($apiPath) || ! str_starts_with($apiPath, '/')) {
            $fail($file.': every path key must begin with /.');
            continue;
        }

        if (str_starts_with($apiPath, '/api/') || str_starts_with($apiPath, '/v1/')) {
            $fail($file.' '.$apiPath.': paths are relative to the /api server and must not add /api or /v1.');
        }

        if (! is_array($pathItem)) {
            $fail($file.' '.$apiPath.': path item must be an object.');
            continue;
        }

        preg_match_all('/\{([^}]+)\}/', $apiPath, $matches);
        $templateParameters = $matches[1] ?? [];

        foreach ($pathItem as $method => $operation) {
            $method = strtolower((string) $method);
            if (! in_array($method, $httpMethods, true)) {
                continue;
            }

            $location = $file.' '.strtoupper($method).' '.$apiPath;
            if (! is_array($operation)) {
                $fail($location.': operation must be an object.');
                continue;
            }

            $operationId = $operation['operationId'] ?? null;
            if (! is_string($operationId) || trim($operationId) === '') {
                $fail($location.': operationId is required.');
            } elseif (isset($operationIds[$operationId])) {
                $fail($location.': duplicate operationId '.$operationId.' (first used by '.$operationIds[$operationId].').');
            } else {
                $operationIds[$operationId] = $location;
            }

            $routeName = $operation['x-laravel-route-name'] ?? null;
            if (! is_string($routeName) || ! str_starts_with($routeName, 'api.')) {
                $fail($location.': x-laravel-route-name must identify the real named API route.');
            }

            $authentication = $operation['x-authentication'] ?? null;
            if (! is_string($authentication) || $authentication === '') {
                $fail($location.': x-authentication is required.');
            }

            if (! array_key_exists('security', $operation) || ! is_array($operation['security'])) {
                $fail($location.': security must explicitly describe authenticated, optional, or anonymous access.');
            }

            if (! is_array($operation['responses'] ?? null) || ($operation['responses'] ?? []) === []) {
                $fail($location.': at least one response is required.');
            }

            $parameters = array_merge(
                is_array($pathItem['parameters'] ?? null) ? $pathItem['parameters'] : [],
                is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [],
            );
            $declaredPathParameters = [];
            foreach ($parameters as $parameter) {
                if (! is_array($parameter) || ($parameter['in'] ?? null) !== 'path') {
                    continue;
                }

                $name = $parameter['name'] ?? null;
                if (is_string($name)) {
                    $declaredPathParameters[] = $name;
                }
                if (($parameter['required'] ?? null) !== true) {
                    $fail($location.': path parameters must set required: true.');
                }
            }

            sort($templateParameters);
            sort($declaredPathParameters);
            if ($templateParameters !== $declaredPathParameters) {
                $fail(sprintf(
                    '%s: template parameters [%s] do not match declared path parameters [%s].',
                    $location,
                    implode(', ', $templateParameters),
                    implode(', ', $declaredPathParameters),
                ));
            }

            $pair = $file.'|'.strtoupper($method).'|'.$apiPath;
            if (isset($documentedOperations[$pair])) {
                $fail($location.': duplicate documented method/path pair.');
            }
            $documentedOperations[$pair] = [
                'route_name' => $routeName,
                'authentication' => $authentication,
                'security' => $operation['security'] ?? [],
                'provider_permission' => $operation['x-provider-permission'] ?? null,
                'parameters' => $parameters,
                'request_body' => $operation['requestBody'] ?? null,
                'response_codes' => array_map('strval', array_keys($operation['responses'] ?? [])),
                'location' => $location,
            ];
        }
    }

    $walkReferences($document, $document, $file, $fail);
}

try {
    $app = require $backend.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $app->make(Kernel::class)->bootstrap();
} catch (Throwable $exception) {
    $fail('Laravel could not boot for route-contract comparison: '.$exception->getMessage());
    $app = null;
}

$actualOperations = [];

$contractFileForUri = static function (string $uri): string {
    return match (true) {
        str_starts_with($uri, 'api/admin/') => 'admin.yaml',
        str_starts_with($uri, 'api/auth/') => 'auth.yaml',
        str_starts_with($uri, 'api/customer/') => 'customer.yaml',
        str_starts_with($uri, 'api/provider/') => 'provider.yaml',
        str_starts_with($uri, 'api/partner/') => 'partner.yaml',
        str_starts_with($uri, 'api/midtrans/') => 'webhooks.yaml',
        default => 'public.yaml',
    };
};

$containsMiddleware = static function (array $middleware, string $needle): bool {
    foreach ($middleware as $item) {
        if (str_contains((string) $item, $needle)) {
            return true;
        }
    }

    return false;
};

if ($app !== null) {
    /** @var Route $route */
    foreach ($app['router']->getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'api/')) {
            continue;
        }

        $file = $contractFileForUri($uri);
        $apiPath = '/'.substr($uri, 4);
        $middleware = array_map('strval', $route->gatherMiddleware());
        $requiresSanctum = $containsMiddleware($middleware, 'Authenticate:sanctum')
            || $containsMiddleware($middleware, 'auth:sanctum');
        $isSigned = $containsMiddleware($middleware, 'ValidateSignature')
            || $containsMiddleware($middleware, 'signed');
        $providerPermissions = [];
        foreach ($middleware as $item) {
            if (preg_match('/EnsureProviderApiAccess(?::([^,]+))?$/', $item, $match)
                || preg_match('/provider\.api(?::([^,]+))?$/', $item, $match)) {
                if (! empty($match[1])) {
                    $providerPermissions[] = $match[1];
                }
            }
        }

        foreach ($route->methods() as $method) {
            $method = strtoupper($method);
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            $pair = $file.'|'.$method.'|'.$apiPath;
            $actualOperations[$pair] = [
                'route_name' => (string) $route->getName(),
                'requires_sanctum' => $requiresSanctum,
                'signed' => $isSigned,
                'provider_permissions' => array_values(array_unique($providerPermissions)),
                'uri' => $uri,
            ];
        }
    }
}

foreach ($actualOperations as $pair => $actual) {
    if (! isset($documentedOperations[$pair])) {
        $fail('Undocumented active API operation: '.$pair.' ('.$actual['route_name'].').');
        continue;
    }

    $documented = $documentedOperations[$pair];
    if ($documented['route_name'] !== $actual['route_name']) {
        $fail(sprintf(
            '%s: documented route name %s does not match Laravel route name %s.',
            $documented['location'],
            (string) $documented['route_name'],
            $actual['route_name'],
        ));
    }

    $auth = $documented['authentication'];
    if ($actual['requires_sanctum']) {
        if (! in_array($auth, ['sanctum', 'sanctum+admin-role'], true)) {
            $fail($documented['location'].': Laravel requires Sanctum but the contract does not.');
        }
        if ($documented['security'] === []) {
            $fail($documented['location'].': authenticated operation cannot have an empty security array.');
        }
    } elseif (in_array($auth, ['sanctum', 'sanctum+admin-role'], true)) {
        $fail($documented['location'].': contract requires Sanctum but the Laravel route does not.');
    }

    if (str_starts_with($actual['uri'], 'api/admin/') && $auth !== 'sanctum+admin-role') {
        $fail($documented['location'].': admin operations must document the controller-level admin role check.');
    }

    if ($actual['provider_permissions'] !== []) {
        $permission = $documented['provider_permission'];
        if (! is_string($permission) || ! in_array($permission, $actual['provider_permissions'], true)) {
            $fail(sprintf(
                '%s: x-provider-permission must match one of [%s].',
                $documented['location'],
                implode(', ', $actual['provider_permissions']),
            ));
        }
    }

    if ($actual['signed']) {
        $queryNames = [];
        foreach ($documented['parameters'] as $parameter) {
            if (is_array($parameter) && ($parameter['in'] ?? null) === 'query') {
                $queryNames[] = $parameter['name'] ?? null;
            }
        }
        if (! in_array('signature', $queryNames, true)) {
            $fail($documented['location'].': signed Laravel route must document the signature query parameter.');
        }
    }
}

foreach ($documentedOperations as $pair => $documented) {
    if (! isset($actualOperations[$pair])) {
        $fail('Contract operation has no active Laravel route: '.$documented['location'].'.');
    }
}

if ($app !== null) {
    try {
        $generator = $app->make(Generator::class);
        $generatedDocument = json_decode(
            json_encode(
                $generator(Scramble::getGeneratorConfig('default')),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($generatedDocument['paths'] ?? [] as $apiPath => $pathItem) {
            if (! is_string($apiPath) || ! is_array($pathItem)) {
                continue;
            }

            $file = $contractFileForUri('api'.$apiPath);
            foreach ($pathItem as $method => $generatedOperation) {
                $method = strtolower((string) $method);
                if (! in_array($method, $httpMethods, true) || ! is_array($generatedOperation)) {
                    continue;
                }

                $pair = $file.'|'.strtoupper($method).'|'.$apiPath;
                if (! isset($documentedOperations[$pair])) {
                    continue;
                }

                $documented = $documentedOperations[$pair];
                $generatedCodes = array_map('strval', array_keys($generatedOperation['responses'] ?? []));
                foreach ($generatedCodes as $code) {
                    if (! in_array($code, $documented['response_codes'], true)) {
                        $fail($documented['location'].': response '.$code.' inferred from the controller is undocumented.');
                    }
                }

                $generatedContents = $generatedOperation['requestBody']['content'] ?? [];
                if (! is_array($generatedContents)) {
                    continue;
                }

                foreach ($generatedContents as $contentType => $generatedContent) {
                    if (! is_array($generatedContent)) {
                        continue;
                    }

                    $generatedShape = $schemaShape($generatedContent['schema'] ?? null, $generatedDocument);
                    if ($generatedShape['properties'] === []) {
                        continue;
                    }

                    $requestBody = $documented['request_body'];
                    $documentedContent = is_array($requestBody)
                        ? ($requestBody['content'][$contentType] ?? null)
                        : null;
                    if (! is_array($documentedContent)) {
                        $fail($documented['location'].': inferred '.$contentType.' request body is undocumented.');
                        continue;
                    }

                    $documentedShape = $schemaShape(
                        $documentedContent['schema'] ?? null,
                        $documents[$file],
                    );
                    if ($documentedShape['properties'] !== $generatedShape['properties']) {
                        $fail(sprintf(
                            '%s: request fields [%s] do not match controller-inferred fields [%s].',
                            $documented['location'],
                            implode(', ', $documentedShape['properties']),
                            implode(', ', $generatedShape['properties']),
                        ));
                    }
                    if ($documentedShape['required'] !== $generatedShape['required']) {
                        $fail(sprintf(
                            '%s: required request fields [%s] do not match controller-inferred fields [%s].',
                            $documented['location'],
                            implode(', ', $documentedShape['required']),
                            implode(', ', $generatedShape['required']),
                        ));
                    }

                    $generatedRequired = ($generatedOperation['requestBody']['required'] ?? false) === true;
                    $documentedRequired = (($requestBody['required'] ?? false) === true);
                    if ($generatedRequired !== $documentedRequired) {
                        $fail($documented['location'].': requestBody.required differs from the controller-inferred contract.');
                    }
                }
            }
        }
    } catch (Throwable $exception) {
        $fail('Scramble controller-contract analysis failed: '.$exception->getMessage());
    }
}

if ($failures !== []) {
    fwrite(STDERR, "OpenAPI contract validation FAILED (".count($failures)." issue(s)):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - '.$failure."\n");
    }
    exit(1);
}

$perFileCounts = [];
foreach (array_keys($actualOperations) as $pair) {
    [$file] = explode('|', $pair, 2);
    $perFileCounts[$file] = ($perFileCounts[$file] ?? 0) + 1;
}
ksort($perFileCounts);

echo 'OpenAPI contract validation passed: '
    .count($documents).' documents, '
    .count($actualOperations).' active method/path operations, '
    .count($operationIds).' unique operationIds.'.PHP_EOL;
foreach ($expectedFiles as $file) {
    echo ' - '.$file.': '.($perFileCounts[$file] ?? 0).' active operations'.PHP_EOL;
}
