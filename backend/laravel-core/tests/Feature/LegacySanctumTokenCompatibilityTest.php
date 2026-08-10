<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LegacySanctumTokenCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_user_morph_type_remains_readable_and_is_used_for_new_tokens(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
        ]);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $user->id,
            'name' => 'legacy-token',
            'token' => hash('sha256', 'legacy-token-value'),
            'abilities' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacyToken = PersonalAccessToken::query()
            ->where('name', 'legacy-token')
            ->firstOrFail();

        $this->assertTrue($legacyToken->tokenable->is($user));
        $this->assertSame('App\\Models\\User', $user->getMorphClass());

        $newToken = $user->createToken('new-token')->accessToken;

        $this->assertSame('App\\Models\\User', $newToken->tokenable_type);
    }
}
