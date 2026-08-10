<?php

namespace App\Modules\Media\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class MediaMigrationEntry extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_COPYING = 'copying';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_CUTOVER = 'cutover';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'migration_key',
        'scope',
        'subject_type',
        'subject_id',
        'subject_field',
        'source_disk',
        'source_path',
        'source_fingerprint',
        'target_disk',
        'target_path',
        'target_fingerprint',
        'source_checksum',
        'target_checksum',
        'archive_disk',
        'archive_path',
        'archive_fingerprint',
        'archive_checksum',
        'status',
        'copy_started_at',
        'copied_at',
        'verified_at',
        'cutover_at',
        'archive_verified_at',
        'source_retired_at',
        'source_restored_at',
        'rolled_back_at',
        'retirement_count',
        'rollback_count',
        'error_message',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'retirement_count' => 'integer',
        'rollback_count' => 'integer',
        'copy_started_at' => 'datetime',
        'copied_at' => 'datetime',
        'verified_at' => 'datetime',
        'cutover_at' => 'datetime',
        'archive_verified_at' => 'datetime',
        'source_retired_at' => 'datetime',
        'source_restored_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public static function fingerprint(string $disk, string $path): string
    {
        return hash('sha256', $disk."\0".$path);
    }
}
