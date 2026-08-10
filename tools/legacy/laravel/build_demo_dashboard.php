<?php

$laravelRoot = dirname(__DIR__, 3) . '/backend/laravel-core';
$sourcePath = $laravelRoot . '/resources/views/provider/pages/dashboard/index.blade.php';
$destPath = $laravelRoot . '/resources/views/demo/provider/dashboard.blade.php';

$sourceContent = file_get_contents($sourcePath);

$mockData = <<<'PHP'
@php
    $rupiah = fn ($amount) => 'Rp' . number_format((float) $amount);
    $number = fn ($value) => number_format((float) $value);

    $summaryCards = collect([
        ['icon' => 'revenue', 'title' => 'Total Revenue', 'value' => 'Rp 24.500.000', 'change' => ['label' => '12%']],
        ['icon' => 'booking', 'title' => 'Total Bookings', 'value' => '142', 'change' => ['label' => '5%']],
        ['icon' => 'completed', 'title' => 'Completed', 'value' => '128', 'change' => ['label' => '8%']],
        ['icon' => 'pending', 'title' => 'Pending', 'value' => '14', 'change' => ['label' => '-2%']],
    ]);
    
    $revenueCard = $summaryCards->firstWhere('icon', 'revenue') ?? ['title' => 'Total Revenue', 'value' => 'Rp0', 'change' => ['label' => '0%']];
    $bookingCard = $summaryCards->firstWhere('icon', 'booking') ?? ['title' => 'Total Bookings', 'value' => '0', 'change' => ['label' => '0%']];
    $completedCard = $summaryCards->firstWhere('icon', 'completed') ?? ['title' => 'Completed', 'value' => '0', 'change' => ['label' => '0%']];
    $pendingCard = $summaryCards->firstWhere('icon', 'pending') ?? ['title' => 'Pending', 'value' => '0', 'change' => ['label' => '0%']];

    $staffItems = collect([
        ['name' => 'Dewi Anggraini', 'rating_label' => '4.9', 'total_booking' => 45, 'revenue_label' => 'Rp 8.500.000'],
        ['name' => 'Budi Kurniawan', 'rating_label' => '4.8', 'total_booking' => 38, 'revenue_label' => 'Rp 6.200.000'],
    ])->values();

    $serviceItems = collect([
        ['name' => 'Premium Haircut', 'booking_count' => 56, 'revenue_label' => 'Rp 5.600.000'],
        ['name' => 'Hair Coloring', 'booking_count' => 42, 'revenue_label' => 'Rp 10.500.000'],
        ['name' => 'Hair Spa', 'booking_count' => 28, 'revenue_label' => 'Rp 4.200.000'],
    ])->values();

    $stats = [
        'branches_count' => 2,
        'services_count' => 15,
    ];

    $providerName = 'Aura Studio';
    $firstName = 'Aura';
    
    $periodOptions = ['month' => 'This Month'];
    $selectedPeriod = 'month';
    $periodLabel = 'This Month';
@endphp
PHP;

$animationScript = <<<'HTML'
<style>
    /* Prevent manual clicking on the whole page to make it a pure simulation */
    body {
        pointer-events: none;
    }
    
    /* Subtle theme transition effect */
    body, .admin-main-wrapper, .admin-sidebar, .admin-topbar, .floating-card {
        transition: background-color 0.8s ease, color 0.8s ease, border-color 0.8s ease, box-shadow 0.8s ease !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeBtn = document.getElementById('themeToggleBtn');
        const wait = ms => new Promise(r => setTimeout(r, ms));
        
        async function runThemeSimulation() {
            await wait(3000);
            
            while (true) {
                const darkBtn = document.querySelector('.toggle-theme[title="Dark Theme"]');
                const lightBtn = document.querySelector('.toggle-theme[title="Light Theme"]');
                
                if (darkBtn) darkBtn.click();
                await wait(4000);
                
                if (lightBtn) lightBtn.click();
                await wait(4000);
            }
        }
        
        runThemeSimulation();
    });
</script>
HTML;

// Replace the original @php block
$content = preg_replace('/@php.*?@endphp/s', $mockData, $sourceContent, 1);

// Append JS at the end
$content = str_replace('@endsection', $animationScript . "\n@endsection", $content);

file_put_contents($destPath, $content);
echo "Dashboard demo generated successfully.";
