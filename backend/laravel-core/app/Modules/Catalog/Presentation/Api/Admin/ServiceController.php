<?php

namespace App\Modules\Catalog\Presentation\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $services = Service::query()
            ->with('provider.providerProfile')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('provider', fn ($providerQuery) => $providerQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($services);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        return response()->json(['data' => $service->load('provider.providerProfile', 'bookings')]);
    }

    public function toggleStatus(Request $request, Service $service): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $service->update(['status' => $service->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['message' => 'Status service berhasil diubah.', 'data' => $service->refresh()]);
    }
}
