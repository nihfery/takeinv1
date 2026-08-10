<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status', 'all');
        $gender = $request->get('gender', 'all');

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        if (! in_array($gender, ['all', 'male', 'female', 'other'], true)) {
            $gender = 'all';
        }

        $baseQuery = User::query()
            ->where('role', 'customer');

        $query = (clone $baseQuery)
            ->with('customerProfile')
            ->orderBy('name', 'asc');

        if ($status !== 'all') {
            $query->where(function ($statusQuery) use ($status) {
                if ($status === 'active') {
                    $statusQuery
                        ->whereDoesntHave('customerProfile')
                        ->orWhereHas('customerProfile', function ($profileQuery) {
                            $profileQuery->where('status', 'active');
                        });

                    return;
                }

                $statusQuery->whereHas('customerProfile', function ($profileQuery) {
                    $profileQuery->where('status', 'inactive');
                });
            });
        }

        if ($gender !== 'all') {
            $query->whereHas('customerProfile', function ($profileQuery) use ($gender) {
                $profileQuery->where('gender', $gender);
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('customerProfile', function ($profileQuery) use ($search) {
                        $profileQuery->where('phone_number', 'like', '%' . $search . '%')
                            ->orWhere('gender', 'like', '%' . $search . '%')
                            ->orWhere('status', 'like', '%' . $search . '%');
                    });
            });
        }

        $users = $query->paginate($perPage)->withQueryString();

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->where(function ($query) {
                    $query
                        ->whereDoesntHave('customerProfile')
                        ->orWhereHas('customerProfile', function ($profileQuery) {
                            $profileQuery->where('status', 'active');
                        });
                })
                ->count(),
            'inactive' => (clone $baseQuery)
                ->whereHas('customerProfile', function ($query) {
                    $query->where('status', 'inactive');
                })
                ->count(),
            'profiles' => CustomerProfile::query()->whereHas('user', function ($query) {
                $query->where('role', 'customer');
            })->count(),
        ];

        $filters = [
            'status' => $status,
            'gender' => $gender,
            'search' => $search,
            'per_page' => $perPage,
        ];

        $tabs = [
            'all' => 'All Users',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];

        $hasActiveFilters = $status !== 'all'
            || $gender !== 'all'
            || $search !== ''
            || $perPage !== 10;

        return view('admin.users.index', compact(
            'users',
            'search',
            'perPage',
            'filters',
            'tabs',
            'summary',
            'hasActiveFilters'
        ));
    }

    public function show(User $user)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        if ($user->role !== 'customer') {
            abort(404);
        }

        $user->load('customerProfile');

        return view('admin.users.show', [
            'customer' => $user,
        ]);
    }

    public function toggleStatus(User $user)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        if ($user->role !== 'customer') {
            abort(404);
        }

        $profile = CustomerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'phone_number' => null,
                'gender' => null,
                'date_of_birth' => null,
                'avatar' => null,
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'country' => null,
                'status' => 'active',
            ]
        );

        $profile->update([
            'status' => $profile->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'User status has been updated.');
    }

    public function destroy(User $user)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        if ($user->role !== 'customer') {
            abort(404);
        }

        $user->customerProfile()?->delete();
        $user->delete();

        return back()->with('success', 'User has been deleted.');
    }
}
