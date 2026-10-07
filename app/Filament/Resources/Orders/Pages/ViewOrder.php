<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Downloads\IssueDownloadGrants;
use App\Actions\Orders\CompleteOrder;
use App\Actions\Orders\RefundOrder;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DownloadGrant;
use App\Models\Order;
use App\Notifications\DownloadLinksReady;
use App\Services\Audit\AuditLogger;
use App\Services\PayPal\PayPalOrderService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One order, and everything that can be done about it.
 *
 * The actions that matter are the ones for an order that went wrong: approving
 * a manual review, resending a lost email, reissuing expired links. See §13.4
 * and §13.5.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /** What PayPal says right now, once the admin has asked. */
    public ?string $paypalStatus = null;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->verifyWithPayPalAction(),
            $this->approveAction(),
            $this->resendAction(),
            $this->reissueAction(),
            $this->refundAction(),
            $this->revealEmailAction(),
        ];
    }

    private function verifyWithPayPalAction(): Action
    {
        return Action::make('verify')
            ->label('Verify with PayPal')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Order $record): bool => filled($record->metadata['paypal_order_id'] ?? null))
            ->action(function (Order $record): void {
                try {
                    $remote = app(PayPalOrderService::class)
                        ->retrieve((string) $record->metadata['paypal_order_id']);

                    $this->paypalStatus = (string) ($remote['status'] ?? 'UNKNOWN');

                    Notification::make()
                        ->title("PayPal reports: {$this->paypalStatus}")
                        ->body('Our record says '.$record->status->label().'.')
                        ->info()
                        ->persistent()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Could not reach PayPal')
                        ->body($e->getMessage())->danger()->send();
                }
            });
    }

    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve and deliver')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::ManualReview)
            ->requiresConfirmation()
            ->modalHeading('Approve this order?')
            // Says exactly what is about to happen, in the customer's terms.
            ->modalDescription(fn (Order $record): string => sprintf(
                'This marks %s as paid and emails %d download %s to the customer.',
                $record->number,
                $record->items->sum(fn ($item): int => count($item->file_manifest)),
                $record->items->sum(fn ($item): int => count($item->file_manifest)) === 1 ? 'link' : 'links',
            ))
            ->schema([
                Textarea::make('reason')
                    ->label('Why are you approving this?')
                    ->required()
                    ->rows(2)
                    ->helperText('Recorded in the audit log.'),
            ])
            ->action(function (Order $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $record->forceFill([
                        'status' => OrderStatus::Paid,
                        'paid_at' => $record->paid_at ?? now(),
                        'admin_notes' => trim(($record->admin_notes."\n".$data['reason'])),
                    ])->save();

                    app(CompleteOrder::class)($record->refresh());

                    app(AuditLogger::class)->record('order.approved', $record, [
                        'reason' => $data['reason'],
                        'was' => OrderStatus::ManualReview->value,
                    ]);
                });

                Notification::make()->title('Approved and delivered')
                    ->body('The customer has been emailed their files.')->success()->send();
            });
    }

    private function resendAction(): Action
    {
        return Action::make('resend')
            ->label('Resend download email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->status->isFulfillable())
            ->requiresConfirmation()
            ->action(function (Order $record): void {
                $grants = DownloadGrant::where('order_id', $record->getKey())->get();

                if ($grants->isEmpty()) {
                    Notification::make()->title('No links to send')
                        ->body('Use “Reissue links” instead.')->warning()->send();

                    return;
                }

                // The stored hashes cannot be turned back into links, so
                // resending means issuing a fresh set. See §8.2.
                $this->reissue($record, 'resend');

                Notification::make()->title('Sent')->success()->send();
            });
    }

    private function reissueAction(): Action
    {
        return Action::make('reissue')
            ->label('Reissue links')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->color('gray')
            ->visible(fn (Order $record): bool => $record->status->isFulfillable())
            ->requiresConfirmation()
            ->modalDescription('The old links stop working immediately and a new set is emailed.')
            ->action(function (Order $record): void {
                $this->reissue($record, 'reissue');

                Notification::make()->title('New links sent')
                    ->body('The previous links no longer work.')->success()->send();
            });
    }

    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->payment?->provider_capture_id !== null
                && $record->status->canTransitionTo(OrderStatus::Refunded))
            ->requiresConfirmation()
            ->modalHeading('Refund this order?')
            ->modalDescription(fn (Order $record): string => sprintf(
                'A full refund of %s also revokes the download links we issued.',
                $record->total()->format(),
            ))
            ->schema([
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('$')
                    ->required()
                    ->default(fn (Order $record): string => $record->total()->toDecimalString())
                    ->helperText('Leave as-is for a full refund. A partial one keeps their access.'),

                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->rows(2)
                    ->helperText('Shown to the customer by PayPal, and recorded here.'),
            ])
            ->action(function (Order $record, array $data): void {
                try {
                    app(RefundOrder::class)(
                        $record,
                        $data['reason'],
                        Money::fromDecimal((string) $data['amount'], $record->currency),
                    );

                    Notification::make()->title('Refunded')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('That refund did not go through')
                        ->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    private function revealEmailAction(): Action
    {
        return Action::make('reveal')
            ->label('Reveal email')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Viewing a customer address is recorded in the audit log.')
            ->action(function (Order $record): void {
                app(AuditLogger::class)->record('customer.email_revealed', $record);

                Notification::make()
                    ->title($record->customer->email)
                    ->persistent()
                    ->info()
                    ->send();
            });
    }

    private function reissue(Order $order, string $action): void
    {
        $grants = DB::transaction(function () use ($order) {
            DownloadGrant::where('order_id', $order->getKey())->delete();

            return app(IssueDownloadGrants::class)($order);
        });

        $order->customer->notify(new DownloadLinksReady($order, $grants));

        app(AuditLogger::class)->record("order.{$action}", $order, ['grants' => $grants->count()]);
    }
}
