<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'provider_documents_disk' => env('PROVIDER_DOCUMENTS_DISK', 'provider_documents'),

    'provider_document_url_lifetime' => (int) env('PROVIDER_DOCUMENT_URL_LIFETIME', 5),

    'media' => [
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'media_public'),
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'media_private'),
        'legacy_public_disk' => env('MEDIA_LEGACY_PUBLIC_DISK', 'public'),
        'legacy_archive_disk' => env('MEDIA_LEGACY_ARCHIVE_DISK', 'media_private'),
        'legacy_archive_prefix' => env('MEDIA_LEGACY_ARCHIVE_PREFIX', 'legacy-retirement'),
        'legacy_retirement_enabled' => filter_var(
            env('MEDIA_LEGACY_RETIREMENT_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'legacy_retirement_min_age_days' => (int) env('MEDIA_LEGACY_RETIREMENT_MIN_AGE_DAYS', 30),
        'chat_attachments_disk' => env('CHAT_ATTACHMENTS_DISK', 'media_private'),
        'private_url_lifetime' => (int) env('MEDIA_PRIVATE_URL_LIFETIME', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'provider_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/provider-documents'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'media_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => true,
            'report' => true,
        ],

        'media_private' => [
            'driver' => 'local',
            'root' => storage_path('app/private/media'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'media_public_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'root' => env('MEDIA_PUBLIC_PREFIX', 'public'),
            'visibility' => 'public',
            'throw' => true,
            'report' => true,
        ],

        'media_private_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'root' => env('MEDIA_PRIVATE_PREFIX', 'private'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
