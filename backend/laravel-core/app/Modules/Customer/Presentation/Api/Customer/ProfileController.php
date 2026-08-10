<?php

namespace App\Modules\Customer\Presentation\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $user = $request->user();
        CustomerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active']
        );

        return response()->json([
            'data' => $user->load('customerProfile'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'religion' => ['nullable', 'string', 'max:100'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'address_line_1' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $profile = CustomerProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['status' => 'active']
            );

            $profile->update([
                'phone_number' => $validated['phone_number'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'allergies' => $validated['allergies'] ?? null,
                'address_line_1' => $validated['address_line_1'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'country' => $validated['country'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Profil customer berhasil diperbarui.',
            'data' => $user->refresh()->load('customerProfile'),
        ]);
    }
}
