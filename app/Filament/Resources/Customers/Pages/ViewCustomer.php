<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One customer, with the address still encrypted on the page.
 *
 * Reading it is a deliberate act that leaves a record, the same bargain the
 * order screen makes. See §9.1 and §13.4.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->revealEmailAction(),
        ];
    }

    private function revealEmailAction(): Action
    {
        return Action::make('reveal')
            ->label('Reveal email')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Viewing a customer address is recorded in the audit log.')
            ->action(function (Customer $record): void {
                app(AuditLogger::class)->record('customer.email_revealed', $record);

                Notification::make()
                    ->title($record->email)
                    ->persistent()
                    ->info()
                    ->send();
            });
    }
}
