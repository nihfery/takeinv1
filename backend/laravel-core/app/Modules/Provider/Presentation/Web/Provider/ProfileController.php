<?php

namespace App\Modules\Provider\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Application\Services\AppNotificationService;
use App\Modules\Provider\Application\Services\ProviderDocumentStorage;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProviderDocumentStorage $providerDocuments,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function verification()
    {
        $authId = Auth::id();

        if (! $authId) {
            return redirect()->route('provider.login');
        }

        $user = User::query()->findOrFail($authId);
        $ownerId = ProviderMenuAccess::providerOwnerId($user);

        if (! ProviderMenuAccess::isProviderOwner($user)) {
            $profile = ProviderProfile::query()->where('user_id', $ownerId)->first();

            if ($profile?->document_status === 'verified') {
                return provider_route_redirect('provider.dashboard');
            }

            abort(403, 'Hanya pemilik akun yang dapat mengirim dokumen verifikasi mitra.');
        }

        $profile = ProviderProfile::query()->firstOrCreate(
            ['user_id' => $ownerId],
            ['status' => 'inactive', 'document_status' => 'pending']
        );

        if ($profile->document_status === 'verified') {
            return provider_route_redirect('provider.dashboard');
        }

        $documentUrls = $this->providerDocuments->temporaryProviderUrls($profile);

        return view('provider.pages.verification.index', [
            'user' => $user,
            'profile' => $profile,
            'ktpDocumentUrl' => $documentUrls[ProviderDocumentStorage::KTP],
            'nibDocumentUrl' => $documentUrls[ProviderDocumentStorage::NIB],
        ]);
    }

    public function show()
    {
        $authId = Auth::id();

        if (! $authId) {
            return redirect()->route('provider.login');
        }

        $user = User::query()->findOrFail($authId);

        $profile = ProviderProfile::query()->firstOrCreate(
            ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        $canAccessSensitiveDocuments = ProviderMenuAccess::isProviderOwner($user);
        $documentUrls = $canAccessSensitiveDocuments
            ? $this->providerDocuments->temporaryProviderUrls($profile)
            : [ProviderDocumentStorage::KTP => null, ProviderDocumentStorage::NIB => null];

        return view('provider.pages.profile.show', [
            'user' => $user,
            'profile' => $profile,
            'canAccessSensitiveDocuments' => $canAccessSensitiveDocuments,
            'ktpDocumentUrl' => $documentUrls[ProviderDocumentStorage::KTP],
            'nibDocumentUrl' => $documentUrls[ProviderDocumentStorage::NIB],
        ]);
    }

    public function edit()
    {
        $authId = Auth::id();

        if (! $authId) {
            return redirect()->route('provider.login');
        }

        $user = User::query()->findOrFail($authId);

        $profile = ProviderProfile::query()->firstOrCreate(
            ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        $canAccessSensitiveDocuments = ProviderMenuAccess::isProviderOwner($user);
        $documentUrls = $canAccessSensitiveDocuments
            ? $this->providerDocuments->temporaryProviderUrls($profile)
            : [ProviderDocumentStorage::KTP => null, ProviderDocumentStorage::NIB => null];

        return view('provider.pages.profile.edit', [
            'user' => $user,
            'profile' => $profile,
            'canAccessSensitiveDocuments' => $canAccessSensitiveDocuments,
            'ktpDocumentUrl' => $documentUrls[ProviderDocumentStorage::KTP],
            'nibDocumentUrl' => $documentUrls[ProviderDocumentStorage::NIB],
        ]);
    }

    public function update(Request $request)
    {
        $authId = Auth::id();

        if (! $authId) {
            return redirect()->route('provider.login');
        }

        $user = User::query()->findOrFail($authId);

        if (! ProviderMenuAccess::isProviderOwner($user)) {
            return back()->with('error', 'Branch accounts cannot update the main provider profile.');
        }

        $profile = ProviderProfile::query()->firstOrCreate(
            ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'phone_number' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $beforeIdentifiers = $this->accountIdentifierSnapshot($user);

        DB::beginTransaction();

        try {
            User::query()
                ->whereKey($user->id)
                ->update([
                    'name' => $validated['name'],
                    'username' => $validated['username'] ?? null,
                    'email' => $validated['email'],
                ]);

            $profileData = [
                'phone_number' => $validated['phone_number'] ?? null,
            ];

            if ($request->hasFile('image')) {
                $profileData['image'] = $this->replaceFile(
                    $request,
                    'image',
                    $profile->image,
                    'provider/profile'
                );
            }

            ProviderProfile::query()
                ->whereKey($profile->id)
                ->update($profileData);

            DB::commit();

            $afterIdentifiers = $this->accountIdentifierSnapshot($user->refresh());
            if ($beforeIdentifiers !== $afterIdentifiers) {
                $this->recordAuditEvent->execute(
                    action: 'provider.security.account-identifiers-updated',
                    resourceType: User::class,
                    resourceId: $user->id,
                    before: $beforeIdentifiers,
                    after: $afterIdentifiers,
                    actor: $user,
                    providerId: ProviderMenuAccess::providerOwnerId($user),
                );
            }

            return provider_route_redirect('provider.profile')
                ->with('success', 'Profile has been updated.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Profile update failed. ' . $e->getMessage());
        }
    }

    public function updateDocuments(Request $request)
    {
        $authId = Auth::id();

        if (! $authId) {
            return redirect()->route('provider.login');
        }

        $user = User::query()->findOrFail($authId);

        if (! ProviderMenuAccess::isProviderOwner($user)) {
            return back()->with('error', 'Branch accounts cannot update provider documents.');
        }

        $profile = ProviderProfile::query()->firstOrCreate(
            ['user_id' => ProviderMenuAccess::providerOwnerId($user)],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | If documents are already verified, the provider can no longer change them.
        |--------------------------------------------------------------------------
        */

        if ($profile->document_status === 'verified') {
            return back()->with(
                'error',
                'Documents have already been verified by admin and can no longer be modified.'
            );
        }

        $request->validate([
            'nib_number' => ['required', 'string', 'max:50', 'regex:/^[0-9.\-\s]+$/'],
            'ktp_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'nib_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'business_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'nib_number.required' => 'Nomor Induk Berusaha (NIB) wajib diisi.',
            'nib_number.regex' => 'Nomor NIB hanya boleh berisi angka, spasi, titik, atau tanda hubung.',
            'ktp_image.image' => 'The ID card file must be an image.',
            'ktp_image.mimes' => 'The ID card format must be jpg, jpeg, png, or webp.',
            'ktp_image.max' => 'The ID card file size must not exceed 4MB.',
            'nib_document.mimes' => 'Dokumen NIB harus berupa PDF, JPG, PNG, atau WEBP.',
            'nib_document.max' => 'Ukuran dokumen NIB maksimal 5MB.',
            'business_image.image' => 'The business photo file must be an image.',
            'business_image.mimes' => 'The business photo format must be jpg, jpeg, png, or webp.',
            'business_image.max' => 'The business photo file size must not exceed 4MB.',
        ]);

        if (! $request->hasFile('ktp_image') && ! $request->hasFile('nib_document') && ! $request->hasFile('business_image') && $profile->nib_number === $request->string('nib_number')->trim()->toString()) {
            return back()->with('error', 'Select at least one document to upload.');
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure the provider has 2 documents after submission:
        | 1. Foto KTP
        | 2. Foto Usaha
        |--------------------------------------------------------------------------
        */

        $willHaveKtp = $request->hasFile('ktp_image') || ! empty($profile->ktp_image);
        $willHaveNib = $request->hasFile('nib_document') || ! empty($profile->nib_document);
        $willHaveBusinessImage = $request->hasFile('business_image') || ! empty($profile->business_image);

        if (! $willHaveKtp || ! $willHaveNib || ! $willHaveBusinessImage) {
            return back()->with(
                'error',
                'Upload foto KTP, dokumen NIB, dan foto usaha sebelum mengirim verifikasi.'
            );
        }

        $stagedPrivatePaths = [];
        $stagedPublicPaths = [];
        $replacedPrivatePaths = [];
        $replacedPublicPaths = [];

        DB::beginTransaction();

        try {
            $profileData = [
                'nib_number' => $request->string('nib_number')->trim()->toString(),
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

            if ($request->hasFile('business_image')) {
                $profileData['business_image'] = $request->file('business_image')->store('provider/documents', 'public');

                if (! is_string($profileData['business_image']) || $profileData['business_image'] === '') {
                    throw new \RuntimeException('The business image could not be stored.');
                }

                $stagedPublicPaths[] = $profileData['business_image'];
                $replacedPublicPaths[] = $profile->business_image;
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

            /*
            |--------------------------------------------------------------------------
            | INI BAGIAN PENTING
            |--------------------------------------------------------------------------
            | After documents are submitted successfully, status becomes submitted.
            | Bukan pending lagi.
            */

            $profileData['document_status'] = 'submitted';
            $profileData['document_note'] = null;
            $profileData['document_submitted_at'] = now();
            $profileData['document_verified_at'] = null;

            ProviderProfile::query()
                ->whereKey($profile->id)
                ->update($profileData);

            DB::commit();
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->deleteDocumentFiles(
                $stagedPrivatePaths,
                $stagedPublicPaths,
                (int) $profile->user_id,
                'rollback-cleanup',
            );

            return back()
                ->withInput()
                ->with('error', 'Document upload failed. ' . $e->getMessage());
        }

        $this->deleteDocumentFiles(
            $replacedPrivatePaths,
            $replacedPublicPaths,
            (int) $profile->user_id,
            'post-commit-retirement',
        );

        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->adminRecipients(),
            'provider.document.submitted',
            'Provider documents submitted',
            ($user->name ?: 'Provider') . ' submitted documents for verification.',
            route('admin.providers.show', ProviderMenuAccess::providerOwnerId($user)),
            [
                'provider_id' => ProviderMenuAccess::providerOwnerId($user),
            ],
            (int) $user->id
        );

        return provider_route_redirect('provider.verification')
            ->with('success', 'Dokumen berhasil dikirim. Admin akan memeriksa data Anda sebelum seluruh menu dibuka.');
    }

    public function updatePassword(Request $request)
    {
        $authId = Auth::id();

        if (! $authId) {
            return redirect()->route('provider.login');
        }

        $user = User::query()->findOrFail($authId);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Current password is required.',
            'password.required' => 'New password is required.',
            'password.min' => 'New password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput();
        }

        User::query()
            ->whereKey($user->id)
            ->update([
                'password' => Hash::make($validated['password']),
            ]);

        $this->recordAuditEvent->execute(
            action: 'provider.security.password-changed',
            resourceType: User::class,
            resourceId: $user->id,
            after: ['password_changed' => true],
            actor: $user,
            providerId: ProviderMenuAccess::providerOwnerId($user),
            branchId: $user->branch_id,
        );

        return provider_route_redirect('provider.profile')
            ->with('success', 'Password has been updated.');
    }

    private function replaceFile(Request $request, string $field, ?string $oldPath, string $folder): string
    {
        /*
        |--------------------------------------------------------------------------
        | Store the new file first.
        |--------------------------------------------------------------------------
        | After the new file is stored successfully, delete the old file.
        */

        $newPath = $request->file($field)->store($folder, 'public');

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $newPath;
    }

    private function accountIdentifierSnapshot(User $user): array
    {
        $key = (string) config('app.key', 'audit-fingerprint');

        return [
            'email_fingerprint' => hash_hmac('sha256', mb_strtolower(trim((string) $user->email)), $key),
            'username_fingerprint' => hash_hmac('sha256', mb_strtolower(trim((string) $user->username)), $key),
        ];
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

    public function updateOnboarding(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:not_started,in_progress,skipped,completed',
            'step' => 'nullable|string|in:step_overview,step_dashboard,step_business,step_services,step_branch,step_staff,step_skills,step_schedules,step_setup,step_appointments,step_bookings,step_calendar,step_queue,step_customers,step_finance,step_help,step_finish,setup_branch_add,setup_branch_basic,setup_branch_location,setup_branch_schedule,setup_branch_save,setup_branch_staff,setup_branch_staff_save,setup_branch_manage,setup_service_add,setup_service_basic,setup_service_pricing,setup_service_slots,setup_service_continue,setup_service_manage,setup_staff_add,setup_staff_form,setup_staff_save,setup_staff_manage,setup_skills,setup_schedules,setup_calendar_check,setup_finish,done',
        ]);

        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $user = User::find($authId);
        $providerOwnerId = ProviderMenuAccess::providerOwnerId($user);

        $profile = ProviderProfile::where('user_id', $providerOwnerId)->first();
        if ($profile) {
            $profile->onboarding_status = $validated['status'];
            $profile->onboarding_current_step = $validated['step'] ?? null;
            $profile->save();
        }

        return response()->json([
            'success' => true,
            'status' => $validated['status'],
            'step' => $validated['step'] ?? null,
        ]);
    }
}
