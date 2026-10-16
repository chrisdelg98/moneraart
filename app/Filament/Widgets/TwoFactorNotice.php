<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
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
     * Whether the operator looking at this has actually turned it on.
     *
     * Per user, not per store: the notice is addressed to whoever is reading
     * it, and a second operator who has not set it up should still be told.
     */
    public static function isEnabled(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasTwoFactorEnabled();
    }
}
