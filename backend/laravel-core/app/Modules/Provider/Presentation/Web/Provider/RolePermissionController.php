<?php

namespace App\Modules\Provider\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderMenuAccess;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Subscription\Application\Services\ProviderEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    private function providerId(): int
    {
        return ProviderMenuAccess::providerOwnerId(Auth::user());
    }

    public function index()
    {
        $this->authorizeProviderOwner();

        $providerId = $this->providerId();

        $roles = ProviderRole::query()
            ->where('provider_id', $providerId)
            ->with([
                'branch:id,provider_id,branch_name,status',
                'menuPermissions:id,provider_role_id,menu_key',
                'users:id,name,email,provider_id,branch_id,provider_role_id',
            ])
            ->withCount('users')
            ->orderBy('role_name')
            ->get();

        $branches = ProviderBranch::query()
            ->where('provider_id', $providerId)
            ->orderBy('branch_name')
            ->get(['id', 'provider_id', 'branch_name', 'status']);

        $branchAccounts = User::query()
            ->where('provider_id', $providerId)
            ->where('role', 'provider')
            ->with([
                'providerBranch:id,provider_id,branch_name,status',
                'providerRole:id,role_name,status',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'provider_id',
                'branch_id',
                'provider_role_id',
            ]);

        $menuSections = $this->branchAccountMenuSections();
        $menuLabels = ProviderMenuAccess::labels();

        return view('provider.pages.roles-permissions.index', compact(
            'roles',
            'branches',
            'branchAccounts',
            'menuSections',
            'menuLabels'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeProviderOwner();

        $validated = $this->validatedRoleData($request);
        
        $entitlement = null;
        $createdRole = null;

        DB::transaction(function () use ($validated, &$entitlement, &$createdRole) {
            $user = Auth::user();
            $providerId = $this->providerId();
            
            \App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile::where('user_id', $providerId)->lockForUpdate()->first();

            $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit($user, 'roles_permissions');
            if (!$entitlement['allowed']) {
                return;
            }

            $role = ProviderRole::create([
                'provider_id' => $providerId,
                'branch_id' => $validated['branch_id'] ?? null,
                'role_name' => $validated['role_name'],
                'slug' => $this->uniqueSlug($validated['role_name'], $providerId),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncPermissions($role, $validated['menu_keys'] ?? []);
            $this->createBranchAccount($role, $validated);
            $createdRole = $role;
        });

        if ($entitlement && !$entitlement['allowed']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $entitlement['reason']
                ], 403);
            }
            return redirect()->back()->with('error', $entitlement['reason']);
        }

        if ($createdRole instanceof ProviderRole) {
            $this->recordAuditEvent->execute(
                action: 'provider.role-permissions.created',
                resourceType: ProviderRole::class,
                resourceId: $createdRole->id,
                after: $this->auditSnapshot($createdRole),
                actor: Auth::user(),
                providerId: $this->providerId(),
                branchId: $createdRole->branch_id,
            );
        }

        return provider_route_redirect('provider.roles-permissions.index')
            ->with('success', 'Branch account and permissions have been created.');
    }

    public function update(Request $request, ProviderRole $role)
    {
        $this->authorizeProviderOwner();
        $this->authorizeProviderRole($role);

        $validated = $this->validatedRoleData($request, $role);
        $providerId = $this->providerId();
        $before = $this->auditSnapshot($role);

        DB::transaction(function () use ($validated, $providerId, $role) {
            $role->update([
                'branch_id' => $validated['branch_id'] ?? null,
                'role_name' => $validated['role_name'],
                'slug' => $this->uniqueSlug($validated['role_name'], $providerId, $role->id),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncPermissions($role, $validated['menu_keys'] ?? []);
            $this->updateBranchAccount($role, $validated);
        });

        $role->refresh();
        $after = $this->auditSnapshot($role);
        $before['password_changed'] = false;
        $after['password_changed'] = filled($validated['account_password'] ?? null);
        $this->recordAuditEvent->execute(
            action: 'provider.role-permissions.updated',
            resourceType: ProviderRole::class,
            resourceId: $role->id,
            before: $before,
            after: $after,
            actor: Auth::user(),
            providerId: $providerId,
            branchId: $role->branch_id,
        );

        return provider_route_redirect('provider.roles-permissions.index')
            ->with('success', 'Branch account and permissions have been updated.');
    }

    public function toggleStatus(ProviderRole $role)
    {
        $this->authorizeProviderOwner();
        $this->authorizeProviderRole($role);

        $before = $this->auditSnapshot($role);

        $role->update([
            'status' => $role->status === 'active' ? 'inactive' : 'active',
        ]);

        $this->recordAuditEvent->execute(
            action: 'provider.role-permissions.status-updated',
            resourceType: ProviderRole::class,
            resourceId: $role->id,
            before: $before,
            after: $this->auditSnapshot($role->refresh()),
            actor: Auth::user(),
            providerId: $this->providerId(),
            branchId: $role->branch_id,
        );

        return provider_route_redirect('provider.roles-permissions.index')
            ->with('success', 'Status role berhasil diperbarui.');
    }

    public function destroy(ProviderRole $role)
    {
        $this->authorizeProviderOwner();
        $this->authorizeProviderRole($role);

        $before = $this->auditSnapshot($role);

        $hasUsers = $role->users()->exists();
        $hasStaffs = $role->staffs()->exists();

        if ($hasUsers || $hasStaffs) {
            $role->update(['status' => 'inactive']);
            $this->recordAuditEvent->execute(
                action: 'provider.role-permissions.deactivated',
                resourceType: ProviderRole::class,
                resourceId: $role->id,
                before: $before,
                after: $this->auditSnapshot($role->refresh()),
                actor: Auth::user(),
                providerId: $this->providerId(),
                branchId: $role->branch_id,
            );

            return provider_route_redirect('provider.roles-permissions.index')
                ->with('success', 'Role telah dinonaktifkan karena masih digunakan oleh Staff atau Akun.');
        }

        DB::transaction(function () use ($role) {
            $role->delete();
        });

        $this->recordAuditEvent->execute(
            action: 'provider.role-permissions.deleted',
            resourceType: ProviderRole::class,
            resourceId: $role->id,
            before: $before,
            after: ['deleted' => true],
            actor: Auth::user(),
            providerId: $this->providerId(),
            branchId: $role->branch_id,
        );

        return provider_route_redirect('provider.roles-permissions.index')
            ->with('success', 'Role berhasil dihapus secara permanen.');
    }

    private function validatedRoleData(Request $request, ?ProviderRole $role = null): array
    {
        $providerId = $this->providerId();
        $menuKeys = collect($this->branchAccountMenuSections())
            ->flatMap(fn (array $section) => $section['items'])
            ->pluck('key')
            ->all();

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($role?->users()->value('id')),
            ],
            'account_password' => [
                $role ? 'nullable' : 'required',
                'string',
                'min:8',
            ],
            'branch_id' => [
                'required',
                Rule::exists('provider_branches', 'id')
                    ->where(fn ($query) => $query->where('provider_id', $providerId)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'menu_keys' => ['nullable', 'array'],
            'menu_keys.*' => ['required', Rule::in($menuKeys)],
        ], [
            'role_name.required' => 'Role name is required.',
            'account_name.required' => 'Branch account name is required.',
            'account_email.required' => 'Branch account email is required.',
            'account_email.email' => 'Branch account email format is invalid.',
            'account_email.unique' => 'Branch account email is already in use.',
            'account_password.required' => 'Branch account password is required.',
            'account_password.min' => 'Branch account password must be at least 8 characters.',
            'branch_id.required' => 'Branch is required.',
            'branch_id.exists' => 'Branch is invalid or does not belong to this provider.',
            'menu_keys.*.in' => 'Menu permission is invalid.',
        ]);

        $validated['menu_keys'] = collect($validated['menu_keys'] ?? [])
            ->map(fn ($key) => (string) $key)
            ->unique()
            ->values()
            ->all();

        return $validated;
    }

    private function syncPermissions(ProviderRole $role, array $menuKeys): void
    {
        $role->menuPermissions()->delete();

        $allowedMenuKeys = collect($this->branchAccountMenuSections())
            ->flatMap(fn (array $section) => $section['items'])
            ->pluck('key')
            ->all();

        $permissions = collect($menuKeys)
            ->intersect($allowedMenuKeys)
            ->unique()
            ->map(fn (string $menuKey) => ['menu_key' => $menuKey])
            ->values()
            ->all();

        if ($permissions !== []) {
            $role->menuPermissions()->createMany($permissions);
        }
    }

    private function auditSnapshot(ProviderRole $role): array
    {
        $role->loadMissing([
            'menuPermissions:id,provider_role_id,menu_key',
            'users:id,provider_role_id,branch_id,email,username',
        ]);

        $key = (string) config('app.key', 'audit-fingerprint');

        return [
            'role_name' => $role->role_name,
            'branch_id' => $role->branch_id,
            'status' => $role->status,
            'menu_keys' => $role->menuPermissions
                ->pluck('menu_key')
                ->sort()
                ->values()
                ->all(),
            'account_user_ids' => $role->users
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all(),
            'account_email_fingerprints' => $role->users
                ->map(fn (User $user) => hash_hmac('sha256', mb_strtolower(trim((string) $user->email)), $key))
                ->sort()
                ->values()
                ->all(),
            'account_username_fingerprints' => $role->users
                ->map(fn (User $user) => hash_hmac('sha256', mb_strtolower(trim((string) $user->username)), $key))
                ->sort()
                ->values()
                ->all(),
        ];
    }

    private function createBranchAccount(ProviderRole $role, array $validated): void
    {
        User::create([
            'name' => $validated['account_name'],
            'username' => $this->uniqueUsername($validated['account_name']),
            'email' => $validated['account_email'],
            'password' => Hash::make($validated['account_password']),
            'role' => 'provider',
            'provider_id' => $this->providerId(),
            'branch_id' => $validated['branch_id'],
            'provider_role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function updateBranchAccount(ProviderRole $role, array $validated): void
    {
        $account = $role->users()
            ->where('provider_id', $this->providerId())
            ->where('role', 'provider')
            ->first();

        $payload = [
            'name' => $validated['account_name'],
            'email' => $validated['account_email'],
            'branch_id' => $validated['branch_id'],
            'provider_id' => $this->providerId(),
            'role' => 'provider',
            'provider_role_id' => $role->id,
            'email_verified_at' => now(),
        ];

        if (! empty($validated['account_password'])) {
            $payload['password'] = Hash::make($validated['account_password']);
        }

        if ($account) {
            $account->update($payload);

            return;
        }

        User::create(array_merge($payload, [
            'username' => $this->uniqueUsername($validated['account_name']),
            'password' => Hash::make($validated['account_password'] ?: Str::random(16)),
        ]));
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::slug($name) ?: 'branch-account';
        $username = $base;
        $counter = 2;

        while (User::where('username', $username)->exists()) {
            $username = $base . '-' . $counter;
            $counter++;
        }

        return $username;
    }

    private function authorizeProviderOwner(): void
    {
        $user = Auth::user();

        abort_unless($user && ProviderMenuAccess::isProviderOwner($user), 403, 'Only the main provider account can manage branch accounts.');
    }

    private function uniqueSlug(string $roleName, int $providerId, ?int $ignoreId = null): string
    {
        $base = Str::slug($roleName) ?: 'role';
        $slug = $base;
        $counter = 2;

        while (
            ProviderRole::query()
                ->where('provider_id', $providerId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function authorizeProviderRole(ProviderRole $role): void
    {
        abort_unless((int) $role->provider_id === $this->providerId(), 403, 'Access denied.');
    }

    private function branchAccountMenuSections(): array
    {
        return collect(ProviderMenuAccess::sections())
            ->map(function (array $section) {
                $section['items'] = collect($section['items'])
                    ->reject(fn (array $item) => ($item['key'] ?? null) === 'roles_permissions')
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section) => ! empty($section['items']))
            ->values()
            ->all();
    }
}
