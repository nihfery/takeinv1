<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

$repositoryRoot = realpath(__DIR__.'/../..');

if ($repositoryRoot === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(2);
}

$autoload = $repositoryRoot.'/backend/laravel-core/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "Install backend Composer dependencies before validating workflows.\n");
    exit(2);
}

require $autoload;

$requiredWorkflows = [
    'backend-quality.yml' => 'Backend Quality',
    'backend-tests.yml' => 'Backend Tests',
    'backend-security.yml' => 'Backend Security',
    'customer-web.yml' => 'Customer Web',
    'provider-landing.yml' => 'Provider Landing',
    'contract-tests.yml' => 'Contract Tests',
    'concurrency-tests.yml' => 'Concurrency Tests',
    'dependency-scan.yml' => 'Dependency Scan',
    'secret-scan.yml' => 'Secret Scan',
    'container-scan.yml' => 'Container Scan',
    'build-images.yml' => 'Build Images',
    'deploy-staging.yml' => 'Deploy Staging',
    'deploy-production.yml' => 'Deploy Production',
];

$requiredPaths = [
    'backend/laravel-core/composer.json',
    'backend/laravel-core/composer.lock',
    'backend/laravel-core/Dockerfile',
    'backend/laravel-core/tests/Concurrency/BookingConcurrencyTest.php',
    'apps/customer-web/package.json',
    'apps/customer-web/package-lock.json',
    'apps/provider-landing/package.json',
    'apps/provider-landing/package-lock.json',
    'platform/docker/Dockerfile.next',
    'platform/gateway/backend-http/Dockerfile',
    'tests/contract/validate-openapi.php',
    'tools/ci/check-pint-baseline.php',
    'tools/ci/deploy-remote.sh',
    'tools/ci/php-lint.php',
    'tools/ci/run-concurrency-tests.sh',
    'tools/ci/validate-deploy-env.sh',
    'tools/ci/verify-ci-runs.sh',
];

$errors = [];
$usesCount = 0;

foreach ($requiredPaths as $path) {
    if (! is_file($repositoryRoot.'/'.$path)) {
        $errors[] = "Required workflow command path does not exist: {$path}";
    }
}

foreach ($requiredWorkflows as $filename => $expectedName) {
    $path = $repositoryRoot.'/.github/workflows/'.$filename;

    if (! is_file($path)) {
        $errors[] = "Missing required workflow: {$filename}";
        continue;
    }

    $source = file_get_contents($path);

    if ($source === false) {
        $errors[] = "Unable to read workflow: {$filename}";
        continue;
    }

    try {
        $workflow = Yaml::parse($source);
    } catch (ParseException $exception) {
        $errors[] = "{$filename} is invalid YAML: {$exception->getMessage()}";
        continue;
    }

    if (! is_array($workflow)) {
        $errors[] = "{$filename} must contain a YAML mapping.";
        continue;
    }

    if (($workflow['name'] ?? null) !== $expectedName) {
        $errors[] = "{$filename} must be named '{$expectedName}'.";
    }

    if (! array_key_exists('on', $workflow)) {
        $errors[] = "{$filename} has no trigger configuration.";
    }

    if (! isset($workflow['permissions']) || ! is_array($workflow['permissions'])) {
        $errors[] = "{$filename} must declare least-privilege permissions.";
    }

    if (($workflow['permissions'] ?? null) === 'write-all' || str_contains($source, 'write-all')) {
        $errors[] = "{$filename} may not request write-all permissions.";
    }

    if (! isset($workflow['concurrency']) || ! is_array($workflow['concurrency'])) {
        $errors[] = "{$filename} must define concurrency behavior.";
    }

    if (! isset($workflow['jobs']) || ! is_array($workflow['jobs']) || $workflow['jobs'] === []) {
        $errors[] = "{$filename} has no executable jobs.";
        continue;
    }

    if (str_contains($source, 'pull_request_target')) {
        $errors[] = "{$filename} may not execute privileged code through pull_request_target.";
    }

    foreach ($workflow['jobs'] as $jobName => $job) {
        if (! is_array($job) || ! isset($job['runs-on'])) {
            $errors[] = "{$filename} job {$jobName} must declare runs-on.";
            continue;
        }

        foreach ($job['steps'] ?? [] as $stepIndex => $step) {
            if (! is_array($step) || ! isset($step['uses'])) {
                continue;
            }

            $uses = (string) $step['uses'];
            $usesCount++;

            if (str_starts_with($uses, './')) {
                continue;
            }

            if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*@[a-f0-9]{40}$/', $uses) !== 1) {
                $errors[] = sprintf('%s job %s step %d must pin uses: to a full commit SHA (%s).', $filename, $jobName, $stepIndex + 1, $uses);
            }

            if (str_starts_with($uses, 'actions/checkout@') && (($step['with']['persist-credentials'] ?? null) !== false)) {
                $errors[] = "{$filename} checkout step must disable persisted credentials.";
            }
        }
    }

    if (str_starts_with($filename, 'deploy-')) {
        $expectedEnvironment = $filename === 'deploy-production.yml' ? 'production' : 'staging';
        $trigger = $workflow['on'] ?? null;
        $triggerNames = is_array($trigger) ? array_keys($trigger) : [];

        if ($triggerNames !== ['workflow_dispatch']) {
            $errors[] = "{$filename} must remain manual-only and environment-protected.";
        }

        if (($workflow['jobs']['deploy']['environment'] ?? null) !== $expectedEnvironment) {
            $errors[] = "{$filename} must target the {$expectedEnvironment} GitHub environment.";
        }

        foreach (['secrets.DEPLOY_SSH_KEY', 'secrets.DEPLOY_KNOWN_HOSTS', 'secrets.GITHUB_TOKEN'] as $secretReference) {
            if (! str_contains($source, $secretReference)) {
                $errors[] = "{$filename} is missing required protected reference {$secretReference}.";
            }
        }

        if (str_contains($source, 'continue-on-error: true')) {
            $errors[] = "{$filename} may not suppress deployment errors.";
        }
    }
}

if ($usesCount === 0) {
    $errors[] = 'No pinned GitHub Actions were discovered.';
}

if ($errors !== []) {
    fwrite(STDERR, "Workflow validation failed:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

printf(
    "Workflow validation passed: %d required workflows, %d pinned action references, %d command paths.\n",
    count($requiredWorkflows),
    $usesCount,
    count($requiredPaths),
);
