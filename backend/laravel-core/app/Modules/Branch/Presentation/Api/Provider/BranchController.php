<?php

namespace App\Modules\Branch\Presentation\Api\Provider;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $providerId = $this->providerId($request);

        $branches = ProviderBranch::query()
            ->withCount('staffs')
            ->where('provider_id', $providerId)
            ->latest();
        ProviderAccountScope::applyBranchModelScope($branches, $this->providerBranchId($request));

        $branches = $branches->paginate($this->perPage($request));

        return response()->json($branches);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($this->isProviderBranchAccount($request), 403, 'Akun cabang tidak dapat membuat branch baru.');

        $providerId = $this->providerId($request);
        $validated = $this->validateBranch($request);

        $branch = ProviderBranch::create(array_merge($validated, [
            'provider_id' => $providerId,
            'image' => $this->storeUploadedFile($request, 'image', 'branch-images'),
        ]));

        return response()->json(['message' => 'Branch has been added.', 'data' => $branch], 201);
    }

    public function show(Request $request, ProviderBranch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);

        return response()->json(['data' => $branch->load('staffs')]);
    }

    public function update(Request $request, ProviderBranch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);
        $validated = $this->validateBranch($request, $branch->id);

        $branch->update(array_merge($validated, [
            'image' => $this->replaceUploadedFile($request, 'image', $branch->image, 'branch-images'),
        ]));

        return response()->json(['message' => 'Branch has been updated.', 'data' => $branch->refresh()]);
    }

    public function preview(Request $request, ProviderBranch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);

        $branch->load([
            'provider:id,name,email',
            'provider.providerProfile:user_id,status,document_status,image',
            'staffs' => fn ($query) => $query->where('status', 'active')->with('schedules'),
        ]);

        $branch->loadCount(['staffs' => fn ($query) => $query->where('status', 'active')]);
        $branch->loadCount('branchReviews');
        $branch->loadAvg('branchReviews', 'rating');

        // Note: Booking and payment are disabled on frontend side when in preview mode.
        return response()->json([
            'data' => array_merge($branch->toArray(), [
                'is_preview_mode' => true,
            ]),
        ]);
    }

    public function updateStaff(Request $request, ProviderBranch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);

        $validated = $request->validate([
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer'],
        ]);

        $providerId = $this->providerId($request);
        $staffIds = ProviderStaff::where('provider_id', $providerId)
            ->when($this->providerBranchId($request) !== null, fn ($query) => $query->where('branch_id', $this->providerBranchId($request)))
            ->whereIn('id', $validated['staff_ids'] ?? [])
            ->pluck('id')
            ->toArray();

        DB::transaction(function () use ($branch, $providerId, $staffIds) {
            ProviderStaff::where('provider_id', $providerId)
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            ProviderStaff::where('provider_id', $providerId)
                ->whereIn('id', $staffIds)
                ->update(['branch_id' => $branch->id]);
        });

        return response()->json(['message' => 'Branch staff has been updated.', 'data' => $branch->refresh()->load('staffs')]);
    }

    public function destroy(Request $request, ProviderBranch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);
        abort_if($this->isProviderBranchAccount($request), 403, 'Akun cabang tidak dapat menghapus branch.');

        DB::transaction(function () use ($request, $branch) {
            ProviderStaff::where('provider_id', $this->providerId($request))
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            $this->deleteStoredFile($branch->image);
            $branch->delete();
        });

        return response()->json(['message' => 'Branch has been deleted.']);
    }

    private function validateBranch(Request $request, ?int $branchId = null): array
    {
        return $request->validate([
            'branch_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_code' => ['required', 'string', 'max:20'],
            'phone_number' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string'],
            'country_id' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'zip_code' => ['required', 'string', 'max:20'],
            'working_start_hour' => ['required', 'date_format:H:i'],
            'working_end_hour' => ['required', 'date_format:H:i'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'max:20'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
            'image' => [$branchId ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }

    private function authorizeBranch(Request $request, ProviderBranch $branch): void
    {
        abort_if($branch->provider_id !== $this->providerId($request), 403);
        abort_if($this->providerBranchId($request) !== null && (int) $branch->id !== $this->providerBranchId($request), 403);
    }
}
