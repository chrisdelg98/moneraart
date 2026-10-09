<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Carbon;

/**
 * Filament's dashboard, with a heading that says the time of day.
 *
 * Small, and worth it: the admin is opened first thing in the morning, and a
 * page that greets rather than labels makes the panel feel like somewhere you
 * work rather than a form you were sent to.
 */
class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        $hour = (int) Carbon::now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    public function getSubheading(): ?string
    {
        return "Here's what's happening with your store today.";
    }
}
