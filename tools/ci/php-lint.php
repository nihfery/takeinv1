<?php

declare(strict_types=1);

$repositoryRoot = realpath(__DIR__.'/../..');

if ($repositoryRoot === false) {
    fwrite(STDERR, "Unable to resolve the repository root.\n");
    exit(2);
}

$backendRoot = $repositoryRoot.'/backend/laravel-core';
$excludedSegments = [
    DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR,
];
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($backendRoot, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();

    $excluded = false;

    foreach ($excludedSegments as $segment) {
        if (str_contains($path, $segment)) {
            $excluded = true;
            break;
        }
    }

    if ($excluded) {
        continue;
    }

    $files[] = $path;
}

sort($files, SORT_STRING);

if ($files === []) {
    fwrite(STDERR, "No Laravel PHP files were found to lint.\n");
    exit(2);
}

$failures = [];

foreach ($files as $path) {
    $command = [PHP_BINARY, '-l', $path];
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, $backendRoot);

    if (! is_resource($process)) {
        $failures[] = "Unable to start php -l for {$path}";
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        $relativePath = str_replace('\\', '/', substr($path, strlen($backendRoot) + 1));
        $failures[] = trim("{$relativePath}: {$stdout}{$stderr}");
    }
}

if ($failures !== []) {
    fwrite(STDERR, "PHP syntax failures:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

printf("PHP syntax gate passed for %d files.\n", count($files));
