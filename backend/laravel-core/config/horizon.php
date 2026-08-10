<?php

use Illuminate\Support\Str;

return [
    'name' => env('HORIZON_NAME'),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),

    // "horizon" is reserved internally by the package. Application metadata
    // therefore uses a separately named Laravel Redis connection.
    'use' => env('HORIZON_REDIS_CONNECTION', 'horizon_meta'),
    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web', 'auth:admin'],

    'waits' => [
        'redis:critical' => 15,
        'redis:payments' => 30,
        'redis:bookings' => 30,
        'redis:default' => 60,
        'redis:notifications' => 120,
        'redis:emails' => 180,
        'redis:media' => 300,
        'redis:analytics' => 300,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],
    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,
    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 64),

    // Separate pools guarantee that email/media/analytics load cannot consume
    // every worker needed for booking and payment work.
    'defaults' => [
        'supervisor-critical' => [
            'connection' => 'redis',
            'queue' => ['critical'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => (int) env('HORIZON_WORKER_TRIES', 3),
            'timeout' => (int) env('HORIZON_WORKER_TIMEOUT', 90),
            'backoff' => [5, 15, 60],
            'nice' => 0,
        ],
        'supervisor-business' => [
            'connection' => 'redis',
            'queue' => ['payments', 'bookings', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => (int) env('HORIZON_WORKER_TRIES', 3),
            'timeout' => (int) env('HORIZON_WORKER_TIMEOUT', 90),
            'backoff' => [5, 15, 60],
            'nice' => 0,
        ],
        'supervisor-background' => [
            'connection' => 'redis',
            'queue' => ['notifications', 'emails', 'media', 'analytics'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => (int) env('HORIZON_WORKER_TRIES', 3),
            'timeout' => (int) env('HORIZON_WORKER_TIMEOUT', 90),
            'backoff' => [15, 60, 180],
            'nice' => 5,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-critical' => [
                'maxProcesses' => (int) env('HORIZON_CRITICAL_MAX_PROCESSES', 3),
            ],
            'supervisor-business' => [
                'maxProcesses' => (int) env('HORIZON_BUSINESS_MAX_PROCESSES', 6),
            ],
            'supervisor-background' => [
                'maxProcesses' => (int) env('HORIZON_BACKGROUND_MAX_PROCESSES', 2),
            ],
        ],
        'staging' => [
            'supervisor-critical' => ['maxProcesses' => 2],
            'supervisor-business' => ['maxProcesses' => 3],
            'supervisor-background' => ['maxProcesses' => 1],
        ],
        'local' => [
            'supervisor-critical' => ['maxProcesses' => 1],
            'supervisor-business' => ['maxProcesses' => 1],
            'supervisor-background' => ['maxProcesses' => 1],
        ],
        '*' => [
            'supervisor-critical' => ['maxProcesses' => 1],
            'supervisor-business' => ['maxProcesses' => 1],
            'supervisor-background' => ['maxProcesses' => 1],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
