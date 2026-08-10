@extends('provider.layouts.dashboard')

@section('title', 'Roles & Permissions - Provider Dashboard')
@section('page_title', 'Roles & Permissions')
@section('page_subtitle', 'Create branch accounts and configure provider dashboard menu access.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/roles-permissions.css') }}">
@endpush

@section('content')
@php
    $roles = $roles ?? collect();
    $branches = $branches ?? collect();
    $branchAccounts = $branchAccounts ?? collect();
    $menuSections = $menuSections ?? [];
    $oldMenuKeys = collect(old('menu_keys', []))->map(fn ($key) => (string) $key)->all();
    $totalRoles = $roles->count();
    $activeRoles = $roles->where('status', 'active')->count();
    $inactiveRoles = $roles->where('status', 'inactive')->count();
    $totalBranches = $branches->count();
    $totalBranchAccounts = $branchAccounts->count();
    $totalMenuItems = collect($menuSections)->sum(fn ($section) => count($section['items'] ?? []));
    $roleSummaryCards = [
        [
            'tone' => 'pink',
            'label' => 'Branch Accounts',
            'value' => $totalRoles,
            'hint' => 'Registered login roles',
        ],
        [
            'tone' => 'green',
            'label' => 'Active',
            'value' => $activeRoles,
            'hint' => 'Can access the dashboard',
        ],
        [
            'tone' => 'blue',
            'label' => 'Branch',
            'value' => $totalBranches,
            'hint' => 'Available locations',
        ],
        [
            'tone' => 'orange',
            'label' => 'Menu Access',
            'value' => $totalMenuItems,
            'hint' => 'Permission options',
        ],
    ];

@endphp

<section class="roles-permissions-page provider-roles-page">
    <div class="roles-route">
        <div class="roles-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Roles & Permissions</strong>
        </div>
    </div>

    <div class="roles-header provider-roles-header">
        <div>
            <span class="roles-kicker">Access Control</span>
            <h1>Roles & Permissions</h1>
            <p>Create branch accounts, connect them to branches, and define which dashboard menus they can open.</p>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <button type="button" class="roles-primary-btn" id="roleResetBtn">
                <svg viewBox="0 0 24 24">
                    <path d="M12 5v14"/>
                    <path d="M5 12h14"/>
                </svg>
                New Branch Account
            </button>
        </div>
    </div>

    <div class="roles-summary-grid">
        @foreach ($roleSummaryCards as $card)
            <div class="roles-summary-card {{ $card['tone'] }}">
                <span>{{ $card['label'] }}</span>
                <strong>{{ number_format((int) $card['value']) }}</strong>
                <small>{{ $card['hint'] }}</small>
            </div>
        @endforeach
    </div>

    @if (session('success'))
        <div class="roles-alert success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="roles-alert error">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="roles-alert error">
            Some data is invalid. Please check the branch account form again.
        </div>
    @endif

    <div class="roles-layout">
        <div class="roles-list-panel">
            <div class="role-panel-head">
                <div>
                    <span>Access accounts</span>
                    <h2>Branch Accounts</h2>
                    <p>{{ $activeRoles }} active, {{ $inactiveRoles }} inactive, {{ $totalBranchAccounts }} provider login accounts.</p>
                </div>

                <strong>{{ $totalRoles }}</strong>
            </div>

            <div class="roles-toolbar" data-role-filters>
                <label class="roles-search-control">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M21 21l-4.3-4.3"/>
                    </svg>
                    <input type="search" placeholder="Search account, email, branch, or role" data-role-search>
                </label>

                <select data-role-status-filter aria-label="Filter status role">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <select data-role-branch-filter aria-label="Filter branch">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                    @endforeach
                </select>

                <button type="button" class="roles-secondary-btn roles-filter-reset" data-role-filter-reset>
                    Reset
                </button>
            </div>

            <div class="role-account-table-wrap">
                <table class="role-account-table">
                    <colgroup>
                        <col class="role-col-account">
                        <col class="role-col-branch">
                        <col class="role-col-role">
                        <col class="role-col-status">
                        <col class="role-col-menu">
                        <col class="role-col-action">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Branch</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Menu Access</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($roles as $role)
                            @php
                                $permissionKeys = $role->menuPermissions->pluck('menu_key')->values()->all();
                                $account = $role->users->first();
                                $accountName = $account?->name ?? $role->role_name;
                                $accountInitial = strtoupper(substr($accountName ?: 'B', 0, 1));
                                $branchName = $role->branch->branch_name ?? 'Branch not selected yet';
                                $roleSearchLabel = \Illuminate\Support\Str::lower(implode(' ', array_filter([
                                    $accountName,
                                    $account?->email,
                                    $branchName,
                                    $role->role_name,
                                    $role->description,
                                    $role->status,
                                    ...array_map(fn ($key) => $menuLabels[$key] ?? $key, $permissionKeys),
                                ])));
                                $rolePayload = [
                                    'id' => $role->id,
                                    'role_name' => $role->role_name,
                                    'account_name' => $account?->name,
                                    'account_email' => $account?->email,
                                    'branch_id' => $role->branch_id,
                                    'description' => $role->description,
                                    'status' => $role->status,
                                    'menu_keys' => $permissionKeys,
                                    'update_url' => provider_route('provider.roles-permissions.update', $role->id),
                                ];
                            @endphp

                            <tr
                                data-role-row
                                data-role-label="{{ $roleSearchLabel }}"
                                data-role-status="{{ $role->status }}"
                                data-role-branch="{{ $role->branch_id }}"
                            >
                                <td>
                                    <div class="role-account-cell">
                                        <span class="role-account-avatar">{{ $accountInitial }}</span>
                                        <div>
                                            <strong>{{ $accountName }}</strong>
                                            <small>{{ $account?->email ?? 'Email not created yet' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="role-branch-cell">
                                        <strong>{{ $branchName }}</strong>
                                        <small>{{ $role->branch?->status ? ucfirst($role->branch->status) : 'Branch status is unavailable' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="role-name-cell">
                                    <strong>{{ $role->role_name }}</strong>
                                    @if ($role->description)
                                        <small>{{ $role->description }}</small>
                                    @endif
                                    </div>
                                </td>
                                <td>
                                    <form action="{{ provider_route('provider.roles-permissions.toggle-status', $role->id) }}" method="POST" class="inline-form provider-service-status-form">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="provider-status-toggle status-{{ $role->status === 'active' ? 'success' : 'danger' }}" title="Click to change status">
                                            <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                            {{ ucfirst($role->status) }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="role-permission-tags compact">
                                        @forelse (array_slice($permissionKeys, 0, 3) as $permissionKey)
                                            <span>{{ $menuLabels[$permissionKey] ?? $permissionKey }}</span>
                                        @empty
                                            <span>No menu access</span>
                                        @endforelse

                                        @if (count($permissionKeys) > 3)
                                            <span>+{{ count($permissionKeys) - 3 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="role-table-actions">
                                        <button type="button" class="roles-icon-btn role-edit-btn" data-role='@json($rolePayload)' title="Edit branch account" aria-label="Edit {{ $accountName }}">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M12 20h9"/>
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                            </svg>
                                        </button>

                                        <form action="{{ provider_route('provider.roles-permissions.destroy', $role->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')

                                            @if ($role->can_be_deleted)
                                            <button type="submit" class="roles-icon-btn danger" data-confirm-delete title="Delete branch account" aria-label="Delete {{ $accountName }}">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M3 6h18"/>
                                                    <path d="M8 6V4h8v2"/>
                                                    <path d="M19 6l-1 14H6L5 6"/>
                                                </svg>
                                            </button>
                                            @endif
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="roles-empty">
                                        <strong>No branch accounts yet.</strong>
                                        <span>Create the first branch account, then choose the menus it can open.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="role-mobile-list">
                @forelse ($roles as $role)
                    @php
                        $permissionKeys = $role->menuPermissions->pluck('menu_key')->values()->all();
                        $account = $role->users->first();
                        $accountName = $account?->name ?? $role->role_name;
                        $accountInitial = strtoupper(substr($accountName ?: 'B', 0, 1));
                        $branchName = $role->branch->branch_name ?? 'Branch not selected yet';
                        $roleSearchLabel = \Illuminate\Support\Str::lower(implode(' ', array_filter([
                            $accountName,
                            $account?->email,
                            $branchName,
                            $role->role_name,
                            $role->description,
                            $role->status,
                            ...array_map(fn ($key) => $menuLabels[$key] ?? $key, $permissionKeys),
                        ])));
                        $rolePayload = [
                            'id' => $role->id,
                            'role_name' => $role->role_name,
                            'account_name' => $account?->name,
                            'account_email' => $account?->email,
                            'branch_id' => $role->branch_id,
                            'description' => $role->description,
                            'status' => $role->status,
                            'menu_keys' => $permissionKeys,
                            'update_url' => provider_route('provider.roles-permissions.update', $role->id),
                        ];
                    @endphp

                    <article
                        class="role-mobile-card"
                        data-role-row
                        data-role-label="{{ $roleSearchLabel }}"
                        data-role-status="{{ $role->status }}"
                        data-role-branch="{{ $role->branch_id }}"
                    >
                        <div class="role-mobile-head">
                            <div class="role-account-cell">
                                <span class="role-account-avatar">{{ $accountInitial }}</span>
                                <div>
                                    <strong>{{ $accountName }}</strong>
                                    <small>{{ $account?->email ?? 'Email not created yet' }}</small>
                                </div>
                            </div>

                            <form action="{{ provider_route('provider.roles-permissions.toggle-status', $role->id) }}" method="POST" class="inline-form provider-service-status-form">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="provider-status-toggle status-{{ $role->status === 'active' ? 'success' : 'danger' }}" title="Click to change status">
                                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    {{ ucfirst($role->status) }}
                                </button>
                            </form>
                        </div>

                        <div class="role-mobile-meta">
                            <div>
                                <span>Branch</span>
                                <strong>{{ $branchName }}</strong>
                            </div>

                            <div>
                                <span>Role</span>
                                <strong>{{ $role->role_name }}</strong>
                            </div>
                        </div>

                        @if ($role->description)
                            <p class="role-mobile-description">{{ $role->description }}</p>
                        @endif

                        <div class="role-permission-tags compact">
                            @forelse (array_slice($permissionKeys, 0, 4) as $permissionKey)
                                <span>{{ $menuLabels[$permissionKey] ?? $permissionKey }}</span>
                            @empty
                                <span>No menu access</span>
                            @endforelse

                            @if (count($permissionKeys) > 4)
                                <span>+{{ count($permissionKeys) - 4 }}</span>
                            @endif
                        </div>

                        <div class="role-table-actions">
                            <button type="button" class="roles-icon-btn role-edit-btn" data-role='@json($rolePayload)' title="Edit branch account" aria-label="Edit {{ $accountName }}">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                </svg>
                            </button>

                            <form action="{{ provider_route('provider.roles-permissions.destroy', $role->id) }}" method="POST">
                                @csrf
                                @method('DELETE')

                                @if ($role->can_be_deleted)
                                <button type="submit" class="roles-icon-btn danger" data-confirm-delete title="Delete branch account" aria-label="Delete {{ $accountName }}">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M3 6h18"/>
                                        <path d="M8 6V4h8v2"/>
                                        <path d="M19 6l-1 14H6L5 6"/>
                                    </svg>
                                </button>
                                @endif
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="roles-empty">
                        <strong>No branch accounts yet.</strong>
                        <span>Create the first branch account, then choose the menus it can open.</span>
                    </div>
                @endforelse
            </div>

            <div class="roles-empty role-filter-empty is-hidden" data-role-filter-empty>
                <strong>No matching accounts.</strong>
                <span>Change the keyword or filter to view other branch accounts.</span>
            </div>
        </div>
    </div>

    <div
        class="role-modal-overlay"
        id="roleModal"
        data-open-on-errors="{{ $errors->any() ? '1' : '0' }}"
        aria-hidden="true"
    >
        <div class="role-modal" role="dialog" aria-modal="true" aria-labelledby="roleFormTitle">
            <div class="role-modal-header">
                <div>
                    <span>Branch Account</span>
                    <h2 id="roleFormTitle">Create Branch Account</h2>
                    <p>Complete the branch account, then choose the menus it can open.</p>
                </div>

                <div class="role-modal-header-actions">
                    <span class="role-form-state" id="roleFormState">New</span>
                    <button type="button" class="role-modal-close" id="roleModalClose" aria-label="Close modal">&times;</button>
                </div>
            </div>

            <form
                action="{{ provider_route('provider.roles-permissions.store') }}"
                method="POST"
                class="role-form-panel"
                id="roleForm"
                data-store-url="{{ provider_route('provider.roles-permissions.store') }}"
            >
                @csrf
                <input type="hidden" name="_method" id="roleFormMethod" value="PUT" disabled>

                <div class="role-modal-body">
                    <div class="role-form-section-title">
                        <span>01</span>
                        <div>
                            <strong>Login Account</strong>
                            <small>This data is used by the branch to access the provider dashboard.</small>
                        </div>
                    </div>

                    <div class="role-form-grid account">
                        <div class="role-field">
                            <label for="accountNameInput">Account Name <span>*</span></label>
                            <input
                                type="text"
                                name="account_name"
                                id="accountNameInput"
                                value="{{ old('account_name') }}"
                                placeholder="Example: Bandung Branch Admin"
                                required
                            >
                            @error('account_name') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="role-field">
                            <label for="accountEmailInput">Email Login <span>*</span></label>
                            <input
                                type="email"
                                name="account_email"
                                id="accountEmailInput"
                                value="{{ old('account_email') }}"
                                placeholder="bandung@provider.test"
                                required
                            >
                            @error('account_email') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="role-field">
                            <label for="accountPasswordInput">Password <span id="accountPasswordRequired">*</span></label>
                            <input
                                type="password"
                                name="account_password"
                                id="accountPasswordInput"
                                placeholder="Minimum 8 characters"
                            >
                            @error('account_password') <small>{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="role-form-section-title">
                        <span>02</span>
                        <div>
                            <strong>Role & Branch</strong>
                            <small>Connect the account to a branch and define its access status.</small>
                        </div>
                    </div>

                    <div class="role-form-grid">
                        <div class="role-field">
                            <label for="roleNameInput">Role Name <span>*</span></label>
                            <input
                                type="text"
                                name="role_name"
                                id="roleNameInput"
                                value="{{ old('role_name') }}"
                                placeholder="Example: Branch Manager"
                                required
                            >
                            @error('role_name') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="role-field">
                            <label for="roleBranchSelect">Branch <span>*</span></label>
                            <select name="branch_id" id="roleBranchSelect" required>
                                <option value="">Select branch</option>

                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>
                                        {{ $branch->branch_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="role-field">
                            <label for="roleStatusSelect">Status Role <span>*</span></label>
                            <select name="status" id="roleStatusSelect" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                            @error('status') <small>{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="role-field full">
                        <label for="roleDescriptionInput">Description</label>
                        <textarea
                            name="description"
                            id="roleDescriptionInput"
                            placeholder="Short note for this branch account"
                        >{{ old('description') }}</textarea>
                        @error('description') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="role-section-head">
                        <div>
                            <h3>Menu Access</h3>
                            <p>Choose dashboard menus that this branch account can open.</p>
                        </div>

                        <button type="button" class="roles-mini-btn" id="roleSelectAllMenus">Select all</button>
                    </div>

                    <div class="role-permission-list">
                        @foreach ($menuSections as $section)
                            <div class="role-permission-group">
                                <div class="role-permission-group-head">
                                    <div>
                                        <strong>{{ $section['title'] }}</strong>
                                        <span>{{ count($section['items']) }} menus</span>
                                    </div>

                                    <button type="button" data-section-toggle>Select</button>
                                </div>

                                <div class="role-permission-options">
                                    @foreach ($section['items'] as $item)
                                        @php
                                            $menuKey = $item['key'];
                                            $inputId = 'role-menu-' . $menuKey;
                                        @endphp

                                        <label class="role-permission-option" for="{{ $inputId }}">
                                            <input
                                                type="checkbox"
                                                name="menu_keys[]"
                                                value="{{ $menuKey }}"
                                                id="{{ $inputId }}"
                                                @checked(in_array($menuKey, $oldMenuKeys, true))
                                            >

                                            <span class="role-permission-option-text">
                                                <strong>{{ $item['label'] }}</strong>
                                                <small>{{ $item['description'] }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('menu_keys') <small class="role-error-block">{{ $message }}</small> @enderror
                    @error('menu_keys.*') <small class="role-error-block">{{ $message }}</small> @enderror
                </div>

                <div class="role-form-actions">
                    <button type="button" class="roles-secondary-btn" id="roleCancelEditBtn">Cancel</button>
                    <button type="submit" class="roles-primary-btn" id="roleSubmitBtn">Save Branch Account</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script src="{{ asset('provider/js/roles-permissions.js') }}"></script>
@endsection


