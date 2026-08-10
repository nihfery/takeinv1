<?php

namespace App\Modules\Subscription\Console\Commands;

use Illuminate\Console\Command;

class GrantLegacySubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:grant-legacy-subscriptions {--dry-run : Show what would happen without saving}';
    protected $description = 'Grant a 30-day legacy subscription to all existing verified providers.';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        // Check or create Legacy plan
        $plan = \App\Modules\Subscription\Infrastructure\Persistence\Models\SubscriptionPlan::firstOrCreate(
            ['name' => 'Legacy Plan'],
            [
                'description' => 'Paket gratis untuk provider lama.',
                'price' => 0,
                'duration_days' => 30,
                'max_branches' => 999, // Legacy providers might have multiple branches
                'is_active' => false, // Don't show to new users
            ]
        );

        $providers = \App\Modules\Identity\Infrastructure\Persistence\Models\User::where('role', 'provider')
            ->whereHas('providerProfile', function ($q) {
                $q->where('status', 'active')
                  ->where('document_status', 'verified');
            })
            ->get();

        $this->info("Found {$providers->count()} verified providers.");

        if ($dryRun) {
            $this->info("DRY RUN: Would grant legacy subscription to {$providers->count()} providers.");
            return;
        }

        $successCount = 0;
        $skippedCount = 0;

        foreach ($providers as $provider) {
            $hasSub = \App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription::where('provider_id', $provider->id)
                ->where('subscription_status', 'active')
                ->exists();

            if ($hasSub) {
                $skippedCount++;
                continue;
            }

            \App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription::create([
                'provider_id' => $provider->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'price' => $plan->price,
                'currency' => 'IDR',
                'duration_days' => $plan->duration_days,
                'max_branches' => $plan->max_branches,
                'payment_status' => 'paid',
                'subscription_status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'paid_at' => now(),
                'midtrans_order_id' => 'LEGACY-' . $provider->id . '-' . time(),
            ]);
            $successCount++;
        }

        $this->info("Granted legacy subscriptions to {$successCount} providers.");
        $this->info("Skipped {$skippedCount} providers who already have an active subscription.");
    }
}
