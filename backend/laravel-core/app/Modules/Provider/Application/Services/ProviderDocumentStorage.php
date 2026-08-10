<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\Media\Application\Contracts\MediaStorage;
use App\Modules\Media\Application\Services\MediaReadResolver;
use App\Modules\Media\Domain\MediaVisibility;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderDocumentStorage
{
    private ?MediaStorage $media = null;

    private ?MediaReadResolver $readResolver = null;

    public const KTP = 'ktp';

    public const NIB = 'nib';

    /** @var array<string, string> */
    private const PROFILE_COLUMNS = [
        self::KTP => 'ktp_image',
        self::NIB => 'nib_document',
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_EXTENSIONS = [
        self::KTP => ['jpg', 'jpeg', 'png', 'webp'],
        self::NIB => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ];

    public function __construct(
        ?MediaStorage $media = null,
        ?MediaReadResolver $readResolver = null,
    ) {
        $this->media = $media;
        $this->readResolver = $readResolver;
    }

    public function stage(
        UploadedFile $file,
        int $providerId,
        string $document,
    ): string {
        $this->assertSupportedDocument($document);

        $stored = $this->media()->storeUploadedFile(
            $file,
            sprintf('providers/%d/%s', $providerId, $document),
            MediaVisibility::Private,
            $this->privateDisk(),
        );

        return $stored->path;
    }

    public function response(ProviderProfile $profile, string $document): StreamedResponse
    {
        $path = $this->path($profile, $document);

        abort_if(! $path, 404);

        $location = $this->readResolver()->resolve(
            $this->privateDisk(),
            $path,
            [$this->legacyDisk()],
        );

        abort_if(! $location, 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS[$document], true)) {
            $extension = $document === self::NIB ? 'pdf' : 'jpg';
        }

        return $this->media()->response(
            $location->disk,
            $location->path,
            sprintf('provider-%s.%s', $document, $extension),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
            ],
            'inline',
        );
    }

    /**
     * Delete a document when the provider itself is deleted. Legacy deletion is
     * deliberately opt-in so replacing a document never destroys the fallback.
     */
    public function delete(?string $path, bool $includeLegacy = false): void
    {
        if (! $path) {
            return;
        }

        if ($this->media()->exists($this->privateDisk(), $path)) {
            $this->media()->delete($this->privateDisk(), $path);

            return;
        }

        if ($includeLegacy && $this->media()->exists($this->legacyDisk(), $path)) {
            $this->media()->delete($this->legacyDisk(), $path);
        }
    }

    /** @return array{ktp: ?string, nib: ?string} */
    public function temporaryProviderUrls(ProviderProfile $profile): array
    {
        return [
            self::KTP => $profile->ktp_image
                ? URL::temporarySignedRoute(
                    'provider.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['document' => self::KTP],
                )
                : null,
            self::NIB => $profile->nib_document
                ? URL::temporarySignedRoute(
                    'provider.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['document' => self::NIB],
                )
                : null,
        ];
    }

    /** @return array{ktp: ?string, nib: ?string} */
    public function temporaryApiProviderUrls(ProviderProfile $profile): array
    {
        return [
            self::KTP => $profile->ktp_image
                ? URL::temporarySignedRoute(
                    'api.provider.profile.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['document' => self::KTP],
                )
                : null,
            self::NIB => $profile->nib_document
                ? URL::temporarySignedRoute(
                    'api.provider.profile.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['document' => self::NIB],
                )
                : null,
        ];
    }

    /** @return array{ktp: ?string, nib: ?string} */
    public function temporaryAdminUrls(ProviderProfile $profile): array
    {
        return [
            self::KTP => $profile->ktp_image
                ? URL::temporarySignedRoute(
                    'admin.providers.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['user' => $profile->user_id, 'document' => self::KTP],
                )
                : null,
            self::NIB => $profile->nib_document
                ? URL::temporarySignedRoute(
                    'admin.providers.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['user' => $profile->user_id, 'document' => self::NIB],
                )
                : null,
        ];
    }

    /** @return array{ktp: ?string, nib: ?string} */
    public function temporaryApiAdminUrls(ProviderProfile $profile): array
    {
        return [
            self::KTP => $profile->ktp_image
                ? URL::temporarySignedRoute(
                    'api.admin.providers.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['provider' => $profile->user_id, 'document' => self::KTP],
                )
                : null,
            self::NIB => $profile->nib_document
                ? URL::temporarySignedRoute(
                    'api.admin.providers.documents.show',
                    now()->addMinutes($this->urlLifetimeMinutes()),
                    ['provider' => $profile->user_id, 'document' => self::NIB],
                )
                : null,
        ];
    }

    private function path(ProviderProfile $profile, string $document): ?string
    {
        $this->assertSupportedDocument($document);

        $column = self::PROFILE_COLUMNS[$document];
        $path = $profile->getAttribute($column);

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function assertSupportedDocument(string $document): void
    {
        abort_unless(array_key_exists($document, self::PROFILE_COLUMNS), 404);
    }

    private function privateDisk(): string
    {
        $disk = (string) config('filesystems.provider_documents_disk', 'provider_documents');
        $this->media()->assertDiskVisibility($disk, MediaVisibility::Private);

        return $disk;
    }

    private function legacyDisk(): string
    {
        return (string) config('filesystems.media.legacy_public_disk', 'public');
    }

    private function urlLifetimeMinutes(): int
    {
        return min(15, max(1, (int) config('filesystems.provider_document_url_lifetime', 5)));
    }

    private function media(): MediaStorage
    {
        return $this->media ??= app(MediaStorage::class);
    }

    private function readResolver(): MediaReadResolver
    {
        return $this->readResolver ??= app(MediaReadResolver::class);
    }
}
