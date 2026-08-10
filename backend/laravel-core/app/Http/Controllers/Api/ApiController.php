<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class ApiController extends Controller
{
    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
    }

    protected function authorizeRole(Request $request, string|array $roles): void
    {
        $roles = (array) $roles;

        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403, 'Access denied.');
    }

    protected function providerId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless($user->role === 'provider', 403, 'Access denied.');

        return ProviderAccountScope::providerId($user);
    }

    protected function providerBranchId(Request $request): ?int
    {
        return ProviderAccountScope::branchId($request->user());
    }

    protected function isProviderBranchAccount(Request $request): bool
    {
        return ProviderAccountScope::isBranchAccount($request->user());
    }

    protected function storeUploadedFile(Request $request, string $field, string $folder): ?string
    {
        $file = $request->file($field);

        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store($folder, 'public');
    }

    protected function replaceUploadedFile(Request $request, string $field, ?string $oldPath, string $folder): ?string
    {
        $newPath = $this->storeUploadedFile($request, $field, $folder);

        if ($newPath && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $newPath ?: $oldPath;
    }

    protected function deleteStoredFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
