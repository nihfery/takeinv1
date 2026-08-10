<?php

namespace App\Modules\Provider\Presentation\Api\Provider;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Application\Services\AppNotificationService;
use App\Modules\Provider\Application\Services\ProviderDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends ApiController
{
    public function __construct(
        private readonly ProviderDocumentStorage $providerDocuments,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $providerId = $this->providerId($request);

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $providerId],
            ['status' => 'active', 'document_status' => 'pending']
        );

        $profile->load('activeSubscription');
        $documentUrls = $this->isProviderBranchAccount($request)
            ? [ProviderDocumentStorage::KTP => null, ProviderDocumentStorage::NIB => null]
            : $this->providerDocuments->temporaryApiProviderUrls($profile);

        return response()->json([
            'data' => $request->user()->load('providerProfile'),
            'profile' => $profile,
            'subscription_active' => $profile->hasActiveSubscription(),
            'active_subscription' => $profile->activeSubscription,
            'document_access' => [
                'has_ktp_document' => $profile->has_ktp_document,
                'has_nib_document' => $profile->has_nib_document,
                'ktp_url' => $documentUrls[ProviderDocumentStorage::KTP],
                'nib_url' => $documentUrls[ProviderDocumentStorage::NIB],
            ],
        ]);
    }

    public function document(Request $request, string $document): Response
    {
        $providerId = $this->providerId($request);
        abort_if($this->isProviderBranchAccount($request), 403);

        $profile = ProviderProfile::query()
            ->where('user_id', $providerId)
            ->firstOrFail();
        $response = $this->providerDocuments->response($profile, $document);

        $this->audit->execute(
            'provider.document.accessed',
            ProviderProfile::class,
            $profile->id,
            after: ['document' => $document, 'channel' => 'api'],
            actor: $request->user(),
            providerId: $providerId,
        );

        return $response;
    }

    public function update(Request $request): JsonResponse
    {
        abort_if($this->isProviderBranchAccount($request), 403, 'Akun cabang tidak boleh mengubah profil utama provider.');

        $providerId = $this->providerId($request);
        $user = User::findOrFail($providerId);
        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active', 'document_status' => 'pending']
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $beforeIdentifiers = $this->accountIdentifierSnapshot($user);

        DB::transaction(function () use ($request, $user, $profile, $validated) {
            $user->update([
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
            ]);

            $profile->update([
                'phone_number' => $validated['phone_number'] ?? null,
                'image' => $this->replaceUploadedFile($request, 'image', $profile->image, 'provider/profile'),
            ]);
        });

        $afterIdentifiers = $this->accountIdentifierSnapshot($user->refresh());
        if ($beforeIdentifiers !== $afterIdentifiers) {
            $this->audit->execute(
                action: 'provider.security.account-identifiers-updated',
                resourceType: User::class,
                resourceId: $user->id,
                before: $beforeIdentifiers,
                after: $afterIdentifiers,
                actor: $request->user(),
                providerId: $providerId,
            );
        }

        return response()->json(['message' => 'Profile berhasil diperbarui.', 'data' => $user->load('providerProfile')]);
    }

    public function updateDocuments(Request $request): JsonResponse
    {
        abort_if($this->isProviderBranchAccount($request), 403, 'Akun cabang tidak boleh mengubah dokumen provider.');

        $providerId = $this->providerId($request);
        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $providerId],
            ['status' => 'active', 'document_status' => 'pending']
        );

        if ($profile->document_status === 'verified') {
            return response()->json(['message' => 'Dokumen sudah verified dan tidak bisa dimodifikasi lagi.'], 422);
        }

        $request->validate([
            'nib_number' => ['required', 'string', 'max:50', 'regex:/^[0-9.\-\s]+$/'],
            'ktp_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'nib_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'business_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        abort_unless(
            $request->hasFile('ktp_image') || $request->hasFile('nib_document') || $request->hasFile('business_image') || $profile->nib_number !== $request->string('nib_number')->trim()->toString(),
            422,
            'Please select at least one document.'
        );

        $willHaveKtp = $request->hasFile('ktp_image') || ! empty($profile->ktp_image);
        $willHaveNib = $request->hasFile('nib_document') || ! empty($profile->nib_document);
        $willHaveBusinessImage = $request->hasFile('business_image') || ! empty($profile->business_image);

        abort_unless($willHaveKtp && $willHaveNib && $willHaveBusinessImage, 422, 'Upload Foto KTP, dokumen NIB, dan Foto Usaha terlebih dahulu.');

        $stagedPrivatePaths = [];
        $stagedPublicPaths = [];
        $replacedPrivatePaths = [];
        $replacedPublicPaths = [];

        DB::beginTransaction();

        try {
            $profileData = [
                'nib_number' => $request->string('nib_number')->trim()->toString(),
                'document_status' => 'submitted',
                'document_note' => null,
                'document_submitted_at' => now(),
                'document_verified_at' => null,
            ];

            if ($request->hasFile('ktp_image')) {
                $profileData['ktp_image'] = $this->providerDocuments->stage(
                    $request->file('ktp_image'),
                    (int) $profile->user_id,
                    ProviderDocumentStorage::KTP,
                );
                $stagedPrivatePaths[] = $profileData['ktp_image'];
                $replacedPrivatePaths[] = $profile->ktp_image;
            }

            if ($request->hasFile('nib_document')) {
                $profileData['nib_document'] = $this->providerDocuments->stage(
                    $request->file('nib_document'),
                    (int) $profile->user_id,
                    ProviderDocumentStorage::NIB,
                );
                $stagedPrivatePaths[] = $profileData['nib_document'];
                $replacedPrivatePaths[] = $profile->nib_document;
            }

            if ($request->hasFile('business_image')) {
                $profileData['business_image'] = $request->file('business_image')->store('provider/documents', 'public');

                if (! is_string($profileData['business_image']) || $profileData['business_image'] === '') {
                    throw new \RuntimeException('The business image could not be stored.');
                }

                $stagedPublicPaths[] = $profileData['business_image'];
                $replacedPublicPaths[] = $profile->business_image;
            }

            $profile->update($profileData);
            DB::commit();
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->deleteDocumentFiles(
                $stagedPrivatePaths,
                $stagedPublicPaths,
                (int) $profile->user_id,
                'rollback-cleanup',
            );

            throw $exception;
        }

        $this->deleteDocumentFiles(
            $replacedPrivatePaths,
            $replacedPublicPaths,
            (int) $profile->user_id,
            'post-commit-retirement',
        );

        $provider = User::query()->find($providerId);

        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->adminRecipients(),
            'provider.document.submitted',
            'Dokumen provider dikirim',
            (($provider?->name ?: $request->user()?->name) ?: 'Provider') . ' mengirim dokumen untuk diverifikasi.',
            route('admin.providers.show', $providerId),
            [
                'provider_id' => (int) $providerId,
            ],
            (int) $request->user()?->id
        );

        $profile->refresh();
        $documentUrls = $this->providerDocuments->temporaryApiProviderUrls($profile);

        return response()->json([
            'message' => 'Dokumen berhasil dikirim.',
            'data' => $profile,
            'document_access' => [
                'has_ktp_document' => $profile->has_ktp_document,
                'has_nib_document' => $profile->has_nib_document,
                'ktp_url' => $documentUrls[ProviderDocumentStorage::KTP],
                'nib_url' => $documentUrls[ProviderDocumentStorage::NIB],
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $this->providerId($request);
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        abort_unless(Hash::check($validated['current_password'], $user->password), 422, 'Password lama tidak sesuai.');

        $user->update(['password' => Hash::make($validated['password'])]);

        $this->audit->execute(
            action: 'provider.security.password-changed',
            resourceType: User::class,
            resourceId: $user->id,
            after: ['password_changed' => true],
            actor: $user,
            providerId: $this->providerId($request),
            branchId: $user->branch_id,
        );

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }

    /**
     * @param  array<int, string|null>  $privatePaths
     * @param  array<int, string|null>  $publicPaths
     */
    private function deleteDocumentFiles(
        array $privatePaths,
        array $publicPaths,
        int $providerId,
        string $operation,
    ): void {
        foreach (array_filter(array_unique($privatePaths)) as $path) {
            try {
                $this->providerDocuments->delete($path);
            } catch (\Throwable $exception) {
                Log::warning('Provider private document cleanup failed.', [
                    'provider_id' => $providerId,
                    'operation' => $operation,
                    'exception' => $exception::class,
                ]);
            }
        }

        foreach (array_filter(array_unique($publicPaths)) as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $exception) {
                Log::warning('Provider public document cleanup failed.', [
                    'provider_id' => $providerId,
                    'operation' => $operation,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    private function accountIdentifierSnapshot(User $user): array
    {
        $key = (string) config('app.key', 'audit-fingerprint');

        return [
            'email_fingerprint' => hash_hmac('sha256', mb_strtolower(trim((string) $user->email)), $key),
            'username_fingerprint' => hash_hmac('sha256', mb_strtolower(trim((string) $user->username)), $key),
        ];
    }
}
