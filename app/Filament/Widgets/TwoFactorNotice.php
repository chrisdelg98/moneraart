<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * A standing reminder that the panel has one lock on it.
 *
 * Optional by decision, which makes a nagging banner the honest shape: it
 * does not block the work, and it does not let the choice be forgotten
 * either. It disappears the day two-factor is switched on.
 *
 * When it is built, the privacy policy gets its sentence back — the claim was
 * removed from it precisely because it was not true. See §16.
 */
class TwoFactorNotice extends Widget
{
    protected string $view = 'filament.widgets.two-factor-notice';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return ! self::isEnabled();
    }

    /**
     * Not built yet, and said in one place so the day it is, this widget and
     * the privacy policy stop disagreeing with reality together.
     */
    public static function isEnabled(): bool
    {
        return false;
    }
}
