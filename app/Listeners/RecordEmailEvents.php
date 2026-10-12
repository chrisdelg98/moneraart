<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\EmailEvent;
use App\Models\Order;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Writes down what became of every email.
 *
 * Laravel already announces both outcomes; nothing listens, so a message SMTP
 * rejected ends up in failed_jobs, which nobody reads. Here a failure is a row
 * the dashboard can count. See §15.
 *
 * What this cannot see is a bounce: generic SMTP accepts a message and tells
 * us nothing about what happened to it afterwards. That needs a provider with
 * webhooks, and the admin is deliberately a plain host and port.
 */
class RecordEmailEvents
{
    public function sent(MessageSent $event): void
    {
        $to = $event->message->getTo()[0] ?? null;

        if ($to === null) {
            return;
        }

        EmailEvent::record(
            email: $to->getAddress(),
            type: $this->notificationFrom($event->data),
            status: EmailEvent::SENT,
            subject: $event->message->getSubject(),
            orderId: $this->orderFromHeader($event->message),
        );
    }

    public function failed(NotificationFailed $event): void
    {
        $email = $event->notifiable->email ?? null;

        if (! is_string($email)) {
            return;
        }

        $exception = $event->data['exception'] ?? null;

        EmailEvent::record(
            email: $email,
            type: $event->notification::class,
            status: EmailEvent::FAILED,
            error: $exception instanceof Throwable ? $exception->getMessage() : null,
            orderId: $this->orderOn($event->notification),
        );
    }

    /** @param array<string, mixed> $data */
    private function notificationFrom(array $data): string
    {
        $notification = $data['__laravel_notification'] ?? null;

        return is_string($notification) ? $notification : 'Mail';
    }

    /**
     * The order a sent message was about.
     *
     * Laravel hands a listener the notification's class name and nothing
     * else, so the notifications that concern an order say which one in a
     * header of their own.
     */
    private function orderFromHeader(Email $message): ?int
    {
        $number = $message->getHeaders()->get('X-Monera-Order')?->getBodyAsString();

        return $number === null
            ? null
            : Order::query()->where('number', $number)->value('id');
    }

    /** Most notifications carry the order they are about; one does not. */
    private function orderOn(mixed $notification): ?int
    {
        $order = is_object($notification) && property_exists($notification, 'order')
            ? $notification->order
            : null;

        return $order instanceof Order ? $order->getKey() : null;
    }
}
