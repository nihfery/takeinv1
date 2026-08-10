<?php

namespace App\Modules\Customer\Presentation\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $customers = User::query()
            ->with('customerProfile')
            ->where('role', 'customer')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile', function ($profileQuery) use ($search) {
                            $profileQuery->where('phone_number', 'like', "%{$search}%")
                                ->orWhere('gender', 'like', "%{$search}%")
                                ->orWhere('religion', 'like', "%{$search}%")
                                ->orWhere('allergies', 'like', "%{$search}%")
                                ->orWhere('status', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json($customers);
    }

    public function show(Request $request, User $customer): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        abort_if($customer->role !== 'customer', 404);

        return response()->json(['data' => $customer->load('customerProfile')]);
    }

    public function toggleStatus(Request $request, User $customer): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        abort_if($customer->role !== 'customer', 404);

        $profile = CustomerProfile::firstOrCreate(
            ['user_id' => $customer->id],
            ['status' => 'active']
        );

        $profile->update(['status' => $profile->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['message' => 'Status user berhasil diubah.', 'data' => $customer->load('customerProfile')]);
    }

    public function destroy(Request $request, User $customer): JsonResponse
    {
        $this->authorizeRole($request, 'admin');
        abort_if($customer->role !== 'customer', 404);

        $customer->customerProfile()?->delete();
        $customer->delete();

        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}
