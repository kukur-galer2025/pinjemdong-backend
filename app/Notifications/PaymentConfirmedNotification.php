<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification
{
    use Queueable;

    public $rental;
    public $payment;

    /**
     * Create a new notification instance.
     */
    public function __construct($rental, $payment)
    {
        $this->rental = $rental;
        $this->payment = $payment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_confirmed',
            'title' => 'Pembayaran Dikonfirmasi',
            'message' => 'Pembayaran Anda untuk pesanan #' . $this->rental->id . ' telah dikonfirmasi.',
            'rental_id' => $this->rental->id,
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
        ];
    }
}
