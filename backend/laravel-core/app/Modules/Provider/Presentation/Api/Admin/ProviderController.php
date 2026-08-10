<?php

namespace App\Modules\Provider\Presentation\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Services\ProviderDocumentStorage;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProviderController extends ApiController
{
    public function __construct(
        private readonly ProviderDocumentStorage $providerDocuments,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $providers = User::query()
            ->with('providerProfile')
            ->where('role', 'provider')
            ->whereNull('provider_id')
            ->whereNull('provider_role_id')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('providerProfile', function ($profileQuery) use ($search) {
                            $profileQuery->where('phone_number', 'like', "%{$search}%")
                                ->orWhere('status', 'like', "%{$search}%")
                                ->orWhere('document_status', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($providers);
    }

    public function show(Request $request, User $provider): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        $this->authorizeProviderOwnerTarget($provider);

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $provider->id],
            ['status' => 'inactive', 'document_status' => 'pending']
        );

        $documentUrls = $this->providerDocuments->temporaryApiAdminUrls($profile);

        return response()->json([
            'data' => $provider->load('providerProfile'),
            'document_access' => [
                'has_ktp_document' => $profile->has_ktp_document,
                'has_nib_document' => $profile->has_nib_document,
                'ktp_url' => $documentUrls[ProviderDocumentStorage::KTP],
                'nib_url' => $documentUrls[ProviderDocumentStorage::NIB],
            ],
        ]);
    }

    public function document(Request $request, User $provider, string $document): Response
    {
        $this->authorizeRole($request, 'admin');
        $this->authorizeProviderOwnerTarget($provider);

        $profile = ProviderProfile::query()
            ->where('user_id', $provider->id)
            ->firstOrFail();

        $response = $this->providerDocuments->response($profile, $document);

        $this->audit->execute(
            'provider.document.accessed',
            ProviderProfile::class,
            $profile->id,
            after: ['document' => $document, 'channel' => 'admin-api'],
            actor: $request->user(),
            providerId: (int) $provider->id,
        );

        return $response;
    }

    public function toggleStatus(Request $request, User $provider): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        $this->authorizeProviderOwnerTarget($provider);

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $provider->id],
            ['status' => 'inactive', 'document_status' => 'pending']
        );

        $before = ['status' => (string) $profile->status];
        $newStatus = $profile->status === 'active' ? 'inactive' : 'active';
        $profile->update(['status' => $newStatus]);

        $this->audit->execute(
            $newStatus === 'active' ? 'admin.provider.activated' : 'admin.provider.suspended',
            User::class,
            $provider->id,
            before: $before,
            after: ['status' => $newStatus],
            actor: $request->user(),
            providerId: (int) $provider->id,
        );

        return response()->json(['message' => 'Status akun provider berhasil diperbarui.', 'data' => $provider->load('providerProfile')]);
    }

    public function updateDocumentStatus(Request $request, User $provider): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        $this->authorizeProviderOwnerTarget($provider);

        $validated = $request->validate([
            'document_status' => ['required', Rule::in(['pending', 'submitted', 'verified', 'rejected'])],
            'document_note' => [Rule::requiredIf($request->input('document_status') === 'rejected'), 'nullable', 'string', 'max:2000'],
        ]);

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $provider->id],
            ['status' => 'inactive', 'document_status' => 'pending']
        );

        abort_if(
            $validated['document_status'] === 'verified' && collect([
                $profile->ktp_image,
                $profile->nib_number,
                $profile->nib_document,
                $profile->business_image,
            ])->contains(fn ($value) => blank($value)),
            422,
            'Dokumen provider belum lengkap.'
        );

        $before = [
            'document_status' => (string) $profile->document_status,
            'status' => (string) $profile->status,
        ];

        $profileData = [
            'document_status' => $validated['document_status'],
            'document_note' => $validated['document_note'] ?? null,
        ];

        if ($validated['document_status'] === 'verified') {
            $profileData['status'] = 'active';
            $profileData['document_verified_at'] = now();
        } else {
            $profileData['document_verified_at'] = null;
        }

        $profile->update($profileData);

        $action = match ($validated['document_status']) {
            'verified' => 'admin.provider.approved',
            'rejected' => 'admin.provider.rejected',
            default => 'admin.provider.document-status-updated',
        };

        $this->audit->execute(
            $action,
            User::class,
            $provider->id,
            before: $before,
            after: [
                'document_status' => (string) $profile->document_status,
                'status' => (string) $profile->status,
            ],
            actor: $request->user(),
            providerId: (int) $provider->id,
        );

        return response()->json(['message' => 'Status dokumen provider berhasil diperbarui.', 'data' => $provider->load('providerProfile')]);
    }

    public function destroy(Request $request, User $provider): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        $this->authorizeProviderOwnerTarget($provider);

        $profile = $provider->providerProfile;
        $before = [
            'status' => (string) ($profile?->status ?? 'inactive'),
            'document_status' => (string) ($profile?->document_status ?? 'pending'),
        ];

        if ($profile) {
            $this->providerDocuments->delete($profile->ktp_image, includeLegacy: true);
            $this->providerDocuments->delete($profile->nib_document, includeLegacy: true);
            $this->deleteStoredFile($profile->business_image);
            $this->deleteStoredFile($profile->image);
            $profile->delete();
        }

        $provider->delete();

        $this->audit->execute(
            'admin.provider.deleted',
            User::class,
            $provider->id,
            before: $before,
            after: ['deleted' => true],
            actor: $request->user(),
            providerId: (int) $provider->id,
        );

        return response()->json(['message' => 'Provider berhasil dihapus.']);
    }

    private function authorizeProviderOwnerTarget(User $provider): void
    {
        abort_unless(
            $provider->role === 'provider'
                && $provider->provider_id === null
                && $provider->provider_role_id === null,
            404,
        );
    }
}
