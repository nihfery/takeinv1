<?php

namespace App\Modules\Catalog\Presentation\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all');
        $documentStatus = (string) $request->get('document_status', 'all');
        $priceType = (string) $request->get('price_type', 'all');
        $sortBy = (string) $request->get('sort_by', 'created_at');
        $sortDirection = strtolower((string) $request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->get('per_page', 10);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $allowedStatuses = ['all', 'active', 'inactive'];
        $allowedDocumentStatuses = ['all', 'verified', 'submitted', 'pending', 'rejected'];
        $allowedPriceTypes = ['all', 'fixed', 'hourly'];
        $sortColumns = [
            'title' => 'services.title',
            'provider' => 'providers.name',
            'category' => 'services.category',
            'code' => 'services.code',
            'price' => 'services.price',
            'status' => 'services.status',
            'document_status' => 'provider_profiles.document_status',
            'created_at' => 'services.created_at',
        ];

        $status = in_array($status, $allowedStatuses, true) ? $status : 'all';
        $documentStatus = in_array($documentStatus, $allowedDocumentStatuses, true) ? $documentStatus : 'all';
        $priceType = in_array($priceType, $allowedPriceTypes, true) ? $priceType : 'all';
        $sortBy = array_key_exists($sortBy, $sortColumns) ? $sortBy : 'created_at';

        $baseQuery = Service::query()
            ->leftJoin('users as providers', 'providers.id', '=', 'services.provider_id')
            ->leftJoin('provider_profiles as provider_profiles', 'provider_profiles.user_id', '=', 'services.provider_id');

        $summary = [
            'total' => (clone $baseQuery)->count('services.id'),
            'active' => (clone $baseQuery)->where('services.status', 'active')->count('services.id'),
            'verified' => (clone $baseQuery)->where('provider_profiles.document_status', 'verified')->count('services.id'),
            'revenue' => (clone $baseQuery)->sum('services.price'),
        ];

        $query = (clone $baseQuery)
            ->select([
                'services.*',
                DB::raw('providers.name as provider_name'),
                DB::raw('providers.email as provider_email'),
                DB::raw('provider_profiles.document_status as provider_document_status'),
            ])
            ->when($status !== 'all', fn ($builder) => $builder->where('services.status', $status))
            ->when($documentStatus !== 'all', function ($builder) use ($documentStatus) {
                if ($documentStatus === 'pending') {
                    $builder->where(function ($query) {
                        $query->whereNull('provider_profiles.document_status')
                            ->orWhere('provider_profiles.document_status', 'pending');
                    });

                    return;
                }

                $builder->where('provider_profiles.document_status', $documentStatus);
            })
            ->when($priceType !== 'all', fn ($builder) => $builder->where('services.price_type', $priceType));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('services.title', 'like', '%' . $search . '%')
                    ->orWhere('services.slug', 'like', '%' . $search . '%')
                    ->orWhere('services.category', 'like', '%' . $search . '%')
                    ->orWhere('services.code', 'like', '%' . $search . '%')
                    ->orWhere('services.status', 'like', '%' . $search . '%')
                    ->orWhere('providers.name', 'like', '%' . $search . '%')
                    ->orWhere('providers.email', 'like', '%' . $search . '%')
                    ->orWhere('provider_profiles.document_status', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy($sortColumns[$sortBy], $sortDirection)
            ->orderByDesc('services.id');

        $services = $query->paginate($perPage)->withQueryString();

        $filters = [
            'status' => $status,
            'search' => $search,
            'per_page' => $perPage,
            'document_status' => $documentStatus,
            'price_type' => $priceType,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $tabs = [
            'all' => 'All Services',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];

        $hasActiveFilters = $search !== ''
            || $status !== 'all'
            || $documentStatus !== 'all'
            || $priceType !== 'all'
            || $perPage !== 10
            || $sortBy !== 'created_at'
            || $sortDirection !== 'desc';

        return view('admin.services.index', compact(
            'services',
            'search',
            'perPage',
            'filters',
            'summary',
            'tabs',
            'sortBy',
            'sortDirection',
            'hasActiveFilters'
        ));
    }

    public function toggleStatus(Service $service)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $service->update([
            'status' => $service->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Service status has been updated.');
    }
}
