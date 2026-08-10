<?php

namespace App\Modules\Catalog\Presentation\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $search = trim((string) $request->get('search', ''));
        $status = $request->get('status', 'all');
        $featured = $request->get('featured', 'all');
        $perPage = (int) $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = strtolower((string) $request->get('sort_direction', 'desc'));

        if (! in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 10;
        }

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        if (! in_array($featured, ['all', 'yes', 'no'], true)) {
            $featured = 'all';
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $sortMap = [
            'name' => 'name',
            'slug' => 'slug',
            'status' => 'status',
            'featured' => 'is_featured',
            'services_count' => 'services_count',
            'created_at' => 'created_at',
        ];

        if (! array_key_exists($sortBy, $sortMap)) {
            $sortBy = 'created_at';
        }

        $query = ServiceCategory::query()
            ->withCount('services')
            ->when($status !== 'all', fn ($categoryQuery) => $categoryQuery->where('status', $status))
            ->when($featured !== 'all', fn ($categoryQuery) => $categoryQuery->where('is_featured', $featured === 'yes'));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy($sortMap[$sortBy], $sortDirection);

        if ($sortBy !== 'name') {
            $query->orderBy('name');
        }

        $categories = $query->paginate($perPage)->withQueryString();
        $summary = [
            'total' => ServiceCategory::query()->count(),
            'active' => ServiceCategory::query()->where('status', 'active')->count(),
            'featured' => ServiceCategory::query()->where('is_featured', true)->count(),
            'services' => Service::query()->whereNotNull('category_id')->count(),
        ];
        $filters = [
            'status' => $status,
            'featured' => $featured,
            'search' => $search,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];
        $tabs = [
            'all' => 'All Category',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
        $hasActiveFilters = $search !== ''
            || $status !== 'all'
            || $featured !== 'all'
            || $perPage !== 10
            || $sortBy !== 'created_at'
            || $sortDirection !== 'desc';

        return view('admin.services.categories.index', compact(
            'categories',
            'filters',
            'hasActiveFilters',
            'search',
            'perPage',
            'sortBy',
            'sortDirection',
            'summary',
            'tabs'
        ));
    }

    public function store(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:service_categories,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_featured' => ['required', Rule::in(['0', '1'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $slug = $validated['slug'] ?: Str::slug($validated['name']);

        $imagePath = null;
        $iconPath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('service-categories/images', 'public');
        }

        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('service-categories/icons', 'public');
        }

        ServiceCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'image' => $imagePath,
            'icon' => $iconPath,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'is_featured' => (bool) $validated['is_featured'],
        ]);

        return back()->with('success', 'Category has been added.');
    }

    public function update(Request $request, ServiceCategory $category)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('service_categories', 'slug')->ignore($category->id),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_featured' => ['required', Rule::in(['0', '1'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $imagePath = $category->image;
        $iconPath = $category->icon;

        if ($request->hasFile('image')) {
            $this->deleteLocalFile($category->image);
            $imagePath = $request->file('image')->store('service-categories/images', 'public');
        }

        if ($request->hasFile('icon')) {
            $this->deleteLocalFile($category->icon);
            $iconPath = $request->file('icon')->store('service-categories/icons', 'public');
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'image' => $imagePath,
            'icon' => $iconPath,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'is_featured' => (bool) $validated['is_featured'],
        ]);

        return back()->with('success', 'Category has been updated.');
    }

    public function toggleFeatured(ServiceCategory $category)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $category->update([
            'is_featured' => ! $category->is_featured,
        ]);

        return back()->with('success', 'Featured category has been updated.');
    }

    public function toggleStatus(ServiceCategory $category)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $category->update([
            'status' => $category->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Category status has been updated.');
    }

    public function destroy(ServiceCategory $category)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $this->deleteLocalFile($category->image);
        $this->deleteLocalFile($category->icon);

        $category->delete();

        return back()->with('success', 'Category has been deleted.');
    }

    private function deleteLocalFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
