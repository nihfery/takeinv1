<?php

namespace Tests\Unit\Media;

use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Domain\MediaVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use RuntimeException;
use Tests\TestCase;

class MediaStorageConfigurationTest extends TestCase
{
    public function test_public_private_and_s3_compatible_disks_have_explicit_visibility(): void
    {
        $this->assertSame('public', config('filesystems.disks.media_public.visibility'));
        $this->assertSame('private', config('filesystems.disks.media_private.visibility'));
        $this->assertSame('s3', config('filesystems.disks.media_public_s3.driver'));
        $this->assertSame('public', config('filesystems.disks.media_public_s3.visibility'));
        $this->assertSame('s3', config('filesystems.disks.media_private_s3.driver'));
        $this->assertSame('private', config('filesystems.disks.media_private_s3.visibility'));
        $this->assertSame('public', config('filesystems.disks.media_public_s3.root'));
        $this->assertSame('private', config('filesystems.disks.media_private_s3.root'));
        $this->assertSame('media_private', config('filesystems.media.legacy_archive_disk'));
        $this->assertSame('legacy-retirement', config('filesystems.media.legacy_archive_prefix'));
        $this->assertFalse(config('filesystems.media.legacy_retirement_enabled'));
        $this->assertSame(30, config('filesystems.media.legacy_retirement_min_age_days'));
        $this->assertTrue(class_exists(AwsS3V3Adapter::class));
    }

    public function test_media_storage_generates_an_object_key_and_stream_checksum_without_using_original_name(): void
    {
        Storage::fake('media_private');
        config(['filesystems.media.private_disk' => 'media_private']);

        $file = UploadedFile::fake()->createWithContent('sensitive original name.png', 'private-content');
        $stored = app(MediaStorage::class)->storeUploadedFile(
            $file,
            'support/chat/42',
            MediaVisibility::Private,
        );

        $this->assertSame('media_private', $stored->disk);
        $this->assertSame(MediaVisibility::Private, $stored->visibility);
        $this->assertStringStartsWith('support/chat/42/', $stored->path);
        $this->assertStringNotContainsString('sensitive', $stored->path);
        $this->assertSame(hash('sha256', 'private-content'), $stored->checksum);
        Storage::disk('media_private')->assertExists($stored->path);
    }

    public function test_private_media_write_fails_closed_when_the_selected_disk_is_public(): void
    {
        Storage::fake('public');
        config(['filesystems.media.private_disk' => 'public']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must declare private visibility');

        app(MediaStorage::class)->storeUploadedFile(
            UploadedFile::fake()->createWithContent('sensitive.png', 'private-content'),
            'support/chat/42',
            MediaVisibility::Private,
        );
    }

    public function test_public_media_uses_the_public_disk_and_explicit_public_visibility(): void
    {
        Storage::fake('media_public');
        config(['filesystems.media.public_disk' => 'media_public']);

        $stored = app(MediaStorage::class)->storeUploadedFile(
            UploadedFile::fake()->createWithContent('provider logo.png', 'public-content'),
            'providers/9',
            MediaVisibility::Public,
        );

        $this->assertSame('media_public', $stored->disk);
        $this->assertSame(MediaVisibility::Public, $stored->visibility);
        $this->assertStringStartsWith('providers/9/', $stored->path);
        Storage::disk('media_public')->assertExists($stored->path);
    }
}
