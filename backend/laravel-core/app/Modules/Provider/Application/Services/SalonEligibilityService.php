<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;

class SalonEligibilityService
{
    /**
     * Check if a specific branch is eligible to be shown to the public.
     * Returns an array with 'is_eligible' boolean and an array of 'reasons' if not eligible.
     */
    public function checkBranchEligibility(ProviderBranch $branch): array
    {
        $reasons = [];

        if ($branch->status !== 'active') {
            $reasons[] = 'Cabang sedang tidak aktif.';
        }

        $provider = $branch->provider;
        if (!$provider) {
            $reasons[] = 'Data Provider tidak ditemukan.';
            return ['is_eligible' => false, 'reasons' => $reasons];
        }

        $profile = $provider->providerProfile;
        if (!$profile) {
            $reasons[] = 'Profil Provider tidak lengkap.';
            return ['is_eligible' => false, 'reasons' => $reasons];
        }

        if ($profile->status !== 'active') {
            $reasons[] = 'Akun Provider sedang tidak aktif.';
        }

        if ($profile->document_status !== 'verified') {
            $reasons[] = 'Dokumen Provider belum diverifikasi oleh Admin.';
        }

        if ($branch->servicesForBranch()->isEmpty()) {
            $reasons[] = 'Cabang belum memiliki layanan (Service) yang aktif.';
        }

        if ($branch->staffs()->count() === 0) {
            $reasons[] = 'Cabang belum memiliki staf.';
        }

        return [
            'is_eligible' => count($reasons) === 0,
            'reasons' => $reasons,
        ];
    }
    public function getSetupChecklist(User $providerUser): array
    {
        $providerOwnerId = \App\Modules\Provider\Application\Support\ProviderMenuAccess::providerOwnerId($providerUser);
        
        $hasActiveBranch = ProviderBranch::where('provider_id', $providerOwnerId)
            ->where('status', 'active')
            ->exists();

        $hasActiveService = \App\Modules\Catalog\Infrastructure\Persistence\Models\Service::where('provider_id', $providerOwnerId)
            ->where('status', 'active')
            ->exists();

        $hasActiveStaff = \App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff::where('provider_id', $providerOwnerId)
            ->whereNotNull('branch_id')
            ->exists();

        // Check if there is at least one staff with skills
        $hasStaffSkill = \App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff::where('provider_id', $providerOwnerId)
            ->has('skills')
            ->exists();

        // Check if there is at least one staff with a schedule
        $hasStaffSchedule = \App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule::whereHas('staff', function ($query) use ($providerOwnerId) {
                $query->where('provider_id', $providerOwnerId);
            })->exists();

        $setupReady = $hasActiveBranch && $hasActiveService && $hasActiveStaff && $hasStaffSkill && $hasStaffSchedule;

        return [
            'has_branch' => $hasActiveBranch,
            'has_service' => $hasActiveService,
            'has_staff' => $hasActiveStaff,
            'has_skill' => $hasStaffSkill,
            'has_schedule' => $hasStaffSchedule,
            'setup_ready' => $setupReady,
        ];
    }
}
