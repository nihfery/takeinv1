<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Identity\Infrastructure\Persistence\Models\AdminProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function show()
    {
        $user = $this->adminUser();
        $profile = $this->profileFor($user);

        return view('admin.profile.index', compact('user', 'profile'));
    }

    public function update(Request $request)
    {
        $user = $this->adminUser();
        $profile = $this->profileFor($user);

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
            'phone_number' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Admin name is required.',
            'email.required' => 'Admin email is required.',
            'email.email' => 'The email format is invalid.',
            'email.unique' => 'This email is already used by another account.',
            'username.unique' => 'This username is already used by another account.',
            'avatar.image' => 'Profile photo must be an image.',
            'avatar.mimes' => 'Profile photo format must be jpg, jpeg, png, or webp.',
            'avatar.max' => 'Profile photo size must not exceed 2MB.',
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

            $profilePayload = [
                'phone_number' => $validated['phone_number'] ?? null,
                'position' => $validated['position'] ?? null,
                'bio' => $validated['bio'] ?? null,
            ];

            if ($request->hasFile('avatar')) {
                $profilePayload['avatar'] = $this->replaceFile(
                    $request,
                    'avatar',
                    $profile->avatar,
                    'admin/profile'
                );
            }

            AdminProfile::query()
                ->whereKey($profile->id)
                ->update($profilePayload);

            DB::commit();

            $afterIdentifiers = $this->accountIdentifierSnapshot($user->refresh());
            if ($beforeIdentifiers !== $afterIdentifiers) {
                $this->recordAuditEvent->execute(
                    action: 'admin.security.account-identifiers-updated',
                    resourceType: User::class,
                    resourceId: $user->id,
                    before: $beforeIdentifiers,
                    after: $afterIdentifiers,
                    actor: $user,
                );
            }

            return redirect()
                ->route('admin.profile')
                ->with('success', 'Admin profile has been updated.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Admin profile could not be updated. ' . $e->getMessage());
        }
    }

    public function updatePassword(Request $request)
    {
        $user = $this->adminUser();

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
            action: 'admin.security.password-changed',
            resourceType: User::class,
            resourceId: $user->id,
            after: ['password_changed' => true],
            actor: $user,
        );

        return redirect()
            ->route('admin.profile')
            ->with('success', 'Admin password has been updated.');
    }

    private function adminUser(): User
    {
        $user = Auth::user();

        abort_if(! $user || $user->role !== 'admin', 403, 'Access denied.');

        return $user;
    }

    private function profileFor(User $user): AdminProfile
    {
        return AdminProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'phone_number' => null,
                'position' => 'Administrator',
                'avatar' => null,
                'bio' => null,
            ]
        );
    }

    private function accountIdentifierSnapshot(User $user): array
    {
        $key = (string) config('app.key', 'audit-fingerprint');

        return [
            'email_fingerprint' => hash_hmac('sha256', mb_strtolower(trim((string) $user->email)), $key),
            'username_fingerprint' => hash_hmac('sha256', mb_strtolower(trim((string) $user->username)), $key),
        ];
    }

    private function replaceFile(Request $request, string $field, ?string $oldPath, string $folder): string
    {
        $newPath = $request->file($field)->store($folder, 'public');

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $newPath;
    }
}
