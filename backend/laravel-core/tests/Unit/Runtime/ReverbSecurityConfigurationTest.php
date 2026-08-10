<?php

namespace Tests\Unit\Runtime;

use Closure;
use Illuminate\Support\Env;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReverbSecurityConfigurationTest extends TestCase
{
    public function test_reverb_uses_explicit_origins_and_disables_client_events(): void
    {
        $this->withEnvironment([
            'APP_ENV' => 'testing',
            'REVERB_ALLOWED_ORIGINS' => 'localhost,127.0.0.1',
        ], function (): void {
            $application = (require config_path('reverb.php'))['apps']['apps'][0];

            $this->assertNotEmpty($application['allowed_origins']);
            $this->assertNotContains('*', $application['allowed_origins']);
            $this->assertSame('none', $application['accept_client_events_from']);
        });
    }

    public function test_production_origin_list_is_trimmed_normalized_and_deduplicated(): void
    {
        $this->withEnvironment([
            'APP_ENV' => 'production',
            'REVERB_ALLOWED_ORIGINS' => 'TakeIn.ID, provider.takein.id,TakeIn.ID',
        ], function (): void {
            $application = (require config_path('reverb.php'))['apps']['apps'][0];

            $this->assertSame(
                ['takein.id', 'provider.takein.id'],
                $application['allowed_origins']
            );
            $this->assertSame('none', $application['accept_client_events_from']);
        });
    }

    public function test_production_rejects_wildcard_or_url_origin_entries(): void
    {
        foreach (['*', '*.takein.id', 'https://takein.id'] as $invalidOrigin) {
            $process = new Process(
                [PHP_BINARY, base_path('artisan'), 'about', '--only=environment'],
                base_path(),
                [
                    'APP_ENV' => 'production',
                    'REVERB_ALLOWED_ORIGINS' => $invalidOrigin,
                ]
            );
            $process->run();

            $this->assertFalse(
                $process->isSuccessful(),
                "Origin [{$invalidOrigin}] should have been rejected."
            );
            $this->assertStringContainsString(
                'explicit comma-separated hostnames',
                $process->getErrorOutput().$process->getOutput()
            );
        }
    }

    private function withEnvironment(array $overrides, Closure $callback): mixed
    {
        $environment = Env::getRepository();
        $previous = [];

        foreach ($overrides as $key => $value) {
            $previous[$key] = [
                'exists' => $environment->has($key),
                'value' => $environment->get($key),
            ];
            $environment->set($key, $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($previous as $key => $state) {
                if ($state['exists']) {
                    $environment->set($key, $state['value']);
                } else {
                    $environment->clear($key);
                }
            }
        }
    }
}
