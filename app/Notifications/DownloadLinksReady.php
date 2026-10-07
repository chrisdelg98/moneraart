<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DownloadGrant;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The email that delivers the purchase.
 *
 * Carries the only copy of the plaintext tokens that will ever exist outside
 * the moment they were generated — the database holds their hashes. See §8.2.
 */
class DownloadLinksReady extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param Collection<int, DownloadGrant> $grants */
    public function __construct(
        public readonly Order $order,
        public readonly Collection $grants,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('items');

        return (new MailMessage)
            ->subject("Your files are ready — order {$this->order->number}")
            ->view('emails.download-links', [
                'order' => $this->order,
                'grants' => $this->grants->each->loadMissing('file'),
            ]);
    }

    public function routeNotificationForMail(object $notifiable): string
    {
        // Decrypted only here, at the moment of sending.
        return $notifiable->email;
    }
}
