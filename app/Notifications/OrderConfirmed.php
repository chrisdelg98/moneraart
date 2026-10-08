<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt, sent the moment a payment is verified.
 *
 * It exists for two reasons. One is the record: the delivery email carries
 * links that expire, this one carries what was bought and what it cost, and
 * stays useful afterwards.
 *
 * The other is the silence. An order held for manual review is paid and not
 * fulfilled, so no delivery email is sent — without this, someone has been
 * charged and heard nothing at all. See §5.2 and §6.5.
 */
class OrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing(['items', 'payment']);

        return (new MailMessage)
            ->subject("Order {$this->order->number} confirmed")
            ->view('emails.order-confirmed', [
                'order' => $this->order,
                // Paid but not fulfilled: the email has to say so plainly
                // rather than promise files that are not coming yet.
                'isHeld' => $this->order->status === OrderStatus::ManualReview,
            ]);
    }

    public function routeNotificationForMail(object $notifiable): string
    {
        // Decrypted only here, at the moment of sending.
        return $notifiable->email;
    }
}
