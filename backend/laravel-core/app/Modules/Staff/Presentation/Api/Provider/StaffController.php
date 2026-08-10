<?php

namespace App\Modules\Staff\Presentation\Api\Provider;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $providerId = $this->providerId($request);

        $staffs = ProviderStaff::query()
            ->where('provider_id', $providerId)
            ->with('branch:id,provider_id,branch_name,status', 'category:id,name,status')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->latest();
        ProviderAccountScope::applyBranchScope($staffs, $this->providerBranchId($request));

        $staffs = $staffs->paginate($this->perPage($request));

        return response()->json($staffs);
    }

    public function store(Request $request): JsonResponse
    {
        $providerId = $this->providerId($request);
        $validated = $this->validateStaff($request);

        $staff = ProviderStaff::create(array_merge($validated, [
            'provider_id' => $providerId,
            'role' => 'staff',
            'image' => $this->storeUploadedFile($request, 'image', 'provider/staffs'),
        ]));

        return response()->json(['message' => 'Staff berhasil ditambahkan.', 'data' => $staff], 201);
    }

    public function show(Request $request, ProviderStaff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        return response()->json(['data' => $staff->load('branch', 'category')]);
    }

    public function update(Request $request, ProviderStaff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        $validated = $this->validateStaff($request, $staff);

        $updates = array_merge($validated, [
            'image' => $this->replaceUploadedFile($request, 'image', $staff->image, 'provider/staffs'),
        ]);

        if (! $staff->provider_role_id) {
            $updates['role'] = 'staff';
        }

        $staff->update($updates);

        return response()->json(['message' => 'Staff berhasil diperbarui.', 'data' => $staff->refresh()->load('branch', 'category')]);
    }

    public function destroy(Request $request, ProviderStaff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        $this->deleteStoredFile($staff->image);
        $staff->delete();

        return response()->json(['message' => 'Staff berhasil dihapus.']);
    }

    private function validateStaff(Request $request, ?ProviderStaff $staff = null): array
    {
        $providerId = $this->providerId($request);

        $validated = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('provider_staffs', 'email')
                    ->where(fn ($query) => $query->where('provider_id', $providerId))
                    ->ignore($staff?->id),
            ],
            'username' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:20'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'country_id' => ['nullable', 'string', 'max:255'],
            'state_id' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'category_id' => ['required', Rule::exists('service_categories', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'branch_id' => [
                'required',
                Rule::exists('provider_branches', 'id')->where(function ($query) use ($providerId, $request) {
                    $query->where('provider_id', $providerId)->where('status', 'active');
                    ProviderAccountScope::applyBranchScope($query, $this->providerBranchId($request), 'id');
                }),
            ],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($this->providerBranchId($request) !== null) {
            $validated['branch_id'] = $this->providerBranchId($request);
        }

        return $validated;
    }

    private function authorizeStaff(Request $request, ProviderStaff $staff): void
    {
        abort_if((int) $staff->provider_id !== $this->providerId($request), 403);
        abort_if($this->providerBranchId($request) !== null && (int) $staff->branch_id !== $this->providerBranchId($request), 403);
    }
}
