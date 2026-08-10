<?php

namespace App\Modules\Catalog\Presentation\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $categories = ServiceCategory::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:service_categories,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_featured' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $category = ServiceCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'image' => $this->storeUploadedFile($request, 'image', 'service-categories/images'),
            'icon' => $this->storeUploadedFile($request, 'icon', 'service-categories/icons'),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'is_featured' => (bool) $validated['is_featured'],
        ]);

        return response()->json([
            'message' => 'Category berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    public function show(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        return response()->json([
            'data' => $serviceCategory->load('subCategories'),
        ]);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('service_categories', 'slug')->ignore($serviceCategory->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_featured' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $serviceCategory->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'image' => $this->replaceUploadedFile($request, 'image', $serviceCategory->image, 'service-categories/images'),
            'icon' => $this->replaceUploadedFile($request, 'icon', $serviceCategory->icon, 'service-categories/icons'),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'is_featured' => (bool) $validated['is_featured'],
        ]);

        return response()->json([
            'message' => 'Category berhasil diperbarui.',
            'data' => $serviceCategory->refresh(),
        ]);
    }

    public function toggleFeatured(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $serviceCategory->update(['is_featured' => ! $serviceCategory->is_featured]);

        return response()->json(['message' => 'Featured category berhasil diubah.', 'data' => $serviceCategory->refresh()]);
    }

    public function toggleStatus(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $serviceCategory->update(['status' => $serviceCategory->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['message' => 'Status category berhasil diubah.', 'data' => $serviceCategory->refresh()]);
    }

    public function destroy(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorizeRole($request, 'admin');

        $this->deleteStoredFile($serviceCategory->image);
        $this->deleteStoredFile($serviceCategory->icon);
        $serviceCategory->delete();

        return response()->json(['message' => 'Category berhasil dihapus.']);
    }
}
