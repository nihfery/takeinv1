<?php

namespace Tests\Feature;

use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_the_manual_tutorial_trigger_and_local_assets(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->get(route('provider.dashboard'))
            ->assertOk()
            ->assertSee('data-provider-tutorial-trigger', false)
            ->assertSee('data-provider-context-guide', false)
            ->assertSee('data-menu-group="overview"', false)
            ->assertSee('data-menu-key="dashboard"', false)
            ->assertSee('provider/js/driver.js', false)
            ->assertSee('provider/js/onboarding.js', false)
            ->assertSee('provider/css/driver.css', false)
            ->assertSee('provider/css/provider-tutorial.css', false)
            ->assertSee('setupState', false);

        $onboardingScript = file_get_contents(public_path('provider/js/onboarding.js'));
        $this->assertStringContainsString('guideDefinitions', $onboardingScript);
        $this->assertStringContainsString("title: 'Menambahkan Staff'", $onboardingScript);
        $this->assertStringContainsString('provider-tour-skip-btn', $onboardingScript);
        $this->assertStringContainsString('nextSetupStageEntry', $onboardingScript);
        $this->assertStringContainsString("'setup_service_basic'", $onboardingScript);
        $this->assertStringContainsString("'setup_staff_form'", $onboardingScript);
        $this->assertStringContainsString('provider:staff-modal-open-create', $onboardingScript);
        $this->assertStringContainsString('provider-tour-practice--inline', $onboardingScript);
        $this->assertStringContainsString('providerTutorialPaused', $onboardingScript);
    }

    public function test_provider_can_save_and_resume_tutorial_progress(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->postJson(route('provider.profile.onboarding.update'), [
                'status' => 'in_progress',
                'step' => 'step_services',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'in_progress',
                'step' => 'step_services',
            ]);

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $provider->id,
            'onboarding_status' => 'in_progress',
            'onboarding_current_step' => 'step_services',
        ]);
    }

    public function test_provider_cannot_save_an_unknown_tutorial_step(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->postJson(route('provider.profile.onboarding.update'), [
                'status' => 'in_progress',
                'step' => 'unknown_step',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step');
    }

    public function test_provider_can_save_a_step_from_the_guided_business_setup(): void
    {
        $provider = $this->verifiedProvider();

        $this
            ->actingAs($provider, 'provider')
            ->postJson(route('provider.profile.onboarding.update'), [
                'status' => 'in_progress',
                'step' => 'setup_schedules',
            ])
            ->assertOk()
            ->assertJsonPath('step', 'setup_schedules');

        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $provider->id,
            'onboarding_status' => 'in_progress',
            'onboarding_current_step' => 'setup_schedules',
        ]);
    }

    private function verifiedProvider(): User
    {
        $provider = User::factory()->create([
            'role' => 'provider',
        ]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        return $provider;
    }
}
