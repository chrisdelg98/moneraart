<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The way back to a purchase, sent to the address that made it.
 *
 * The link is the whole of the authentication, which is why it is short-lived
 * and why it is never shown on screen: whoever asked for it only gets anywhere
 * if they can read the inbox it went to. See §8.7.
 */
class OrderHistoryLink extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your orders')
            ->view('emails.order-history-link', ['url' => $this->url]);
    }

    public function routeNotificationForMail(object $notifiable): string
    {
        // Decrypted only here, at the moment of sending.
        return $notifiable->email;
    }
}
