<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('customer_profiles')->select('id')->orderBy('id')->cursor() as $profile) {
            DB::table('customer_profiles')
                ->where('id', $profile->id)
                ->update(['customer_id' => $this->newCustomerId((int) $profile->id)]);
        }
    }

    public function down(): void
    {
        // Customer IDs are public identifiers. Keep them stable on rollback.
    }

    private function newCustomerId(int $exceptProfileId): string
    {
        do {
            $customerId = Str::upper(Str::random(10));
        } while (DB::table('customer_profiles')
            ->where('customer_id', $customerId)
            ->where('id', '!=', $exceptProfileId)
            ->exists());

        return $customerId;
    }
};
