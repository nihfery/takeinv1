<?php

declare(strict_types=1);

/**
 * Run Pint against the complete Laravel tree while allowing only the exact
 * legacy violations recorded in pint-legacy-baseline.txt. A baseline entry is
 * tied to the SHA-256 of its file, so editing a debt-bearing file requires that
 * file to be formatted instead of silently inheriting the exemption.
 */

$repositoryRoot = realpath(__DIR__.'/../..');

if ($repositoryRoot === false) {
    fwrite(STDERR, "Unable to resolve the repository root.\n");
    exit(2);
}

$backendRoot = $repositoryRoot.'/backend/laravel-core';
$pint = $backendRoot.'/vendor/bin/pint';
$baselinePath = __DIR__.'/pint-legacy-baseline.txt';

foreach ([$pint, $baselinePath] as $requiredPath) {
    if (! is_file($requiredPath)) {
        fwrite(STDERR, "Required CI file is missing: {$requiredPath}\n");
        exit(2);
    }
}

$baseline = [];
$lines = file($baselinePath, FILE_IGNORE_NEW_LINES);

if ($lines === false) {
    fwrite(STDERR, "Unable to read Pint baseline: {$baselinePath}\n");
    exit(2);
}

foreach ($lines as $lineNumber => $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (preg_match('/^([a-f0-9]{64})\s+(.+)$/', $line, $matches) !== 1) {
        fwrite(STDERR, sprintf("Malformed Pint baseline entry on line %d.\n", $lineNumber + 1));
        exit(2);
    }

    $relativePath = str_replace('\\', '/', $matches[2]);

    if (str_starts_with($relativePath, '/') || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1) {
        fwrite(STDERR, "Unsafe Pint baseline path: {$relativePath}\n");
        exit(2);
    }

    if (isset($baseline[$relativePath])) {
        fwrite(STDERR, "Duplicate Pint baseline path: {$relativePath}\n");
        exit(2);
    }

    $baseline[$relativePath] = $matches[1];
}

$command = [PHP_BINARY, $pint, '--test', '--format=json'];
$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptorSpec, $pipes, $backendRoot);

if (! is_resource($process)) {
    fwrite(STDERR, "Unable to start Pint.\n");
    exit(2);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0 && $exitCode !== 1) {
    fwrite(STDERR, "Pint could not complete.\n{$stderr}{$stdout}\n");
    exit($exitCode > 0 ? $exitCode : 2);
}

$violations = [];

if ($exitCode === 1) {
    try {
        $result = json_decode(trim((string) $stdout), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Pint returned invalid JSON: {$exception->getMessage()}\n{$stderr}{$stdout}\n");
        exit(2);
    }

    foreach ($result['files'] ?? [] as $file) {
        if (! is_array($file)) {
            fwrite(STDERR, "Pint JSON did not contain a valid file path.\n");
            exit(2);
        }

        // Laravel Pint currently reports `path` on Windows and `name` on
        // Linux. Accept both documented payload shapes while rejecting an
        // ambiguous response if a future version emits conflicting values.
        $reportedPaths = array_values(array_filter(
            [$file['path'] ?? null, $file['name'] ?? null],
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        if ($reportedPaths === []) {
            fwrite(STDERR, "Pint JSON did not contain a valid file path.\n");
            exit(2);
        }

        $normalizedPaths = array_values(array_unique(array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            $reportedPaths,
        )));

        if (count($normalizedPaths) !== 1) {
            fwrite(STDERR, "Pint JSON contained conflicting file paths.\n");
            exit(2);
        }

        $relativePath = $normalizedPaths[0];
        $absolutePath = $backendRoot.'/'.$relativePath;

        if (str_starts_with($relativePath, '/') || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1) {
            fwrite(STDERR, "Pint reported an unsafe path: {$relativePath}\n");
            exit(2);
        }

        if (! is_file($absolutePath)) {
            fwrite(STDERR, "Pint reported a path outside the expected tree: {$relativePath}\n");
            exit(2);
        }

        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            fwrite(STDERR, "Unable to hash Pint violation: {$relativePath}\n");
            exit(2);
        }

        $violations[$relativePath] = $hash;
    }
}

$unexpected = [];

foreach ($violations as $path => $hash) {
    if (($baseline[$path] ?? null) !== $hash) {
        $unexpected[] = $path;
    }
}

$stale = array_values(array_diff(array_keys($baseline), array_keys($violations)));

if ($unexpected !== [] || $stale !== []) {
    if ($unexpected !== []) {
        fwrite(STDERR, "New or modified files fail Pint:\n - ".implode("\n - ", $unexpected)."\n");
    }

    if ($stale !== []) {
        fwrite(STDERR, "Stale Pint baseline entries must be removed:\n - ".implode("\n - ", $stale)."\n");
    }

    fwrite(STDERR, "Run vendor/bin/pint on the listed files; do not refresh hashes to bypass formatting.\n");
    exit(1);
}

printf("Pint gate passed (%d exact legacy file hashes acknowledged).\n", count($baseline));
