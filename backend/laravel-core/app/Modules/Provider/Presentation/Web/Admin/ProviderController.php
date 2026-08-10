<?php

namespace App\Modules\Provider\Presentation\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Application\Services\AppNotificationService;
use App\Modules\Provider\Application\Services\ProviderDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderDocumentStorage $providerDocuments,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $allowedPerPage = [10, 25, 50, 100];

        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $search = trim((string) $request->get('search', ''));
        $status = $request->get('status', 'all');
        $documentStatus = $request->get('document_status', 'all');

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        if (! in_array($documentStatus, ['all', 'pending', 'submitted', 'verified', 'rejected'], true)) {
            $documentStatus = 'all';
        }

        $baseQuery = User::query()
            ->where('role', 'provider')
            ->whereNull('provider_id')
            ->whereNull('provider_role_id');

        $providers = (clone $baseQuery)
            ->with([
                'providerProfile',
                'branchAccounts' => function ($query) {
                    $query
                        ->select('id', 'name', 'username', 'email', 'provider_id', 'branch_id', 'provider_role_id', 'created_at')
                        ->with([
                            'providerBranch:id,provider_id,branch_name,status',
                            'providerRole:id,provider_id,branch_id,role_name,status',
                            'providerRole.menuPermissions:id,provider_role_id,menu_key',
                        ])
                        ->orderBy('name');
                },
            ])
            ->withCount('branchAccounts')
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where(function ($statusQuery) use ($status) {
                    if ($status === 'active') {
                        $statusQuery->whereHas('providerProfile', function ($profileQuery) {
                            $profileQuery->where('status', 'active');
                        });

                        return;
                    }

                    $statusQuery
                        ->whereDoesntHave('providerProfile')
                        ->orWhereHas('providerProfile', function ($profileQuery) {
                            $profileQuery->where('status', 'inactive');
                        });
                });
            })
            ->when($documentStatus !== 'all', function ($query) use ($documentStatus) {
                $query->where(function ($documentQuery) use ($documentStatus) {
                    if ($documentStatus === 'pending') {
                        $documentQuery
                            ->whereDoesntHave('providerProfile')
                            ->orWhereHas('providerProfile', function ($profileQuery) {
                                $profileQuery->where('document_status', 'pending');
                            });

                        return;
                    }

                    $documentQuery->whereHas('providerProfile', function ($profileQuery) use ($documentStatus) {
                        $profileQuery->where('document_status', $documentStatus);
                    });
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhereHas('providerProfile', function ($profileQuery) use ($search) {
                            $profileQuery->where('phone_number', 'like', '%' . $search . '%')
                                ->orWhere('category', 'like', '%' . $search . '%')
                                ->orWhere('status', 'like', '%' . $search . '%')
                                ->orWhere('document_status', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('branchAccounts', function ($branchAccountQuery) use ($search) {
                            $branchAccountQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%')
                                ->orWhereHas('providerBranch', function ($branchQuery) use ($search) {
                                    $branchQuery->where('branch_name', 'like', '%' . $search . '%');
                                })
                                ->orWhereHas('providerRole', function ($roleQuery) use ($search) {
                                    $roleQuery->where('role_name', 'like', '%' . $search . '%');
                                });
                        });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->whereHas('providerProfile', function ($query) {
                    $query->where('status', 'active');
                })
                ->count(),
            'verified' => (clone $baseQuery)
                ->whereHas('providerProfile', function ($query) {
                    $query->where('document_status', 'verified');
                })
                ->count(),
            'branches' => User::query()
                ->where('role', 'provider')
                ->whereNotNull('provider_id')
                ->count(),
        ];

        $filters = [
            'status' => $status,
            'document_status' => $documentStatus,
            'search' => $search,
            'per_page' => $perPage,
        ];

        $tabs = [
            'all' => 'All Providers',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];

        $hasActiveFilters = $status !== 'all'
            || $documentStatus !== 'all'
            || $search !== ''
            || $perPage !== 10;

        return view('admin.providers.index', compact(
            'providers',
            'perPage',
            'search',
            'filters',
            'tabs',
            'summary',
            'hasActiveFilters'
        ));
    }

    public function show(User $user)
    {
        abort_if($user->role !== 'provider', 404);
        abort_if($user->provider_role_id && ! $user->provider_id, 404);

        if ($user->provider_id) {
            return redirect()->route('admin.providers.show', $user->provider_id);
        }

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        $provider = $user->load('providerProfile');
        $documentUrls = $this->providerDocuments->temporaryAdminUrls($profile);

        return view('admin.providers.show', [
            'provider' => $provider,
            'profile' => $profile,
            'ktpDocumentUrl' => $documentUrls[ProviderDocumentStorage::KTP],
            'nibDocumentUrl' => $documentUrls[ProviderDocumentStorage::NIB],
        ]);
    }

    public function toggleStatus(User $user)
    {
        abort_if($user->role !== 'provider', 404);
        abort_if($user->provider_id, 404);
        abort_if($user->provider_role_id, 404);

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        $before = ['status' => (string) $profile->status];
        $newStatus = $profile->status === 'active' ? 'inactive' : 'active';

        $profile->update([
            'status' => $newStatus,
        ]);

        $this->audit->execute(
            $newStatus === 'active' ? 'admin.provider.activated' : 'admin.provider.suspended',
            User::class,
            $user->id,
            before: $before,
            after: ['status' => $newStatus],
            actor: request()->user('admin'),
            providerId: (int) $user->id,
        );

        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->providerRecipients((int) $user->id),
            'provider.status.' . $newStatus,
            $newStatus === 'active' ? 'Provider account active' : 'Provider account deactivated',
            $newStatus === 'active'
                ? 'Admin activated your provider account.'
                : 'Admin deactivated your provider account.',
            route('provider.dashboard'),
            [
                'provider_id' => (int) $user->id,
                'status' => $newStatus,
            ],
            (int) request()->user()?->id
        );

        return back()->with('success', 'Provider account status has been updated.');
    }

    public function updateDocumentStatus(Request $request, User $user)
    {
        abort_if($user->role !== 'provider', 404);
        abort_if($user->provider_id, 404);
        abort_if($user->provider_role_id, 404);

        $validated = $request->validate([
            'document_status' => ['required', 'in:pending,submitted,verified,rejected'],
            'document_note' => [Rule::requiredIf($request->input('document_status') === 'rejected'), 'nullable', 'string', 'max:2000'],
        ]);

        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'inactive',
                'document_status' => 'pending',
            ]
        );

        if ($validated['document_status'] === 'verified' && collect([
            $profile->ktp_image,
            $profile->nib_number,
            $profile->nib_document,
            $profile->business_image,
        ])->contains(fn ($value) => blank($value))) {
            return back()->withErrors([
                'document_status' => 'Provider belum memiliki KTP, nomor NIB, dokumen NIB, dan foto usaha yang lengkap.',
            ]);
        }

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
            $user->id,
            before: $before,
            after: [
                'document_status' => (string) $profile->document_status,
                'status' => (string) $profile->status,
            ],
            actor: $request->user('admin'),
            providerId: (int) $user->id,
        );

        $statusLabels = [
            'pending' => 'Pending',
            'submitted' => 'Submitted',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
        ];
        $statusLabel = $statusLabels[$validated['document_status']] ?? ucfirst($validated['document_status']);
        $documentNote = trim((string) ($validated['document_note'] ?? ''));
        $body = $documentNote !== '' ? $documentNote : "Provider document status has been updated to {$statusLabel}.";

        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->providerRecipients((int) $user->id, 'profile'),
            'provider.document.' . $validated['document_status'],
            'Document status updated',
            $body,
            route('provider.profile'),
            [
                'provider_id' => (int) $user->id,
                'document_status' => $validated['document_status'],
            ],
            (int) $request->user()?->id
        );

        return back()->with('success', 'Provider document status has been updated.');
    }

    public function destroy(User $user)
    {
        abort_if($user->role !== 'provider', 404);
        abort_if($user->provider_id, 404);
        abort_if($user->provider_role_id, 404);

        $profile = $user->providerProfile;
        $before = [
            'status' => (string) ($profile?->status ?? 'inactive'),
            'document_status' => (string) ($profile?->document_status ?? 'pending'),
        ];

        if ($profile) {
            if ($profile->ktp_image) {
                $this->providerDocuments->delete($profile->ktp_image, includeLegacy: true);
            }

            if ($profile->business_image) {
                Storage::disk('public')->delete($profile->business_image);
            }

            if ($profile->nib_document) {
                $this->providerDocuments->delete($profile->nib_document, includeLegacy: true);
            }

            $profile->delete();
        }

        $user->delete();

        $this->audit->execute(
            'admin.provider.deleted',
            User::class,
            $user->id,
            before: $before,
            after: ['deleted' => true],
            actor: request()->user('admin'),
            providerId: (int) $user->id,
        );

        return redirect()
            ->route('admin.providers.index')
            ->with('success', 'Provider has been deleted.');
    }
}
