<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RentalStatusUpdatedNotification extends Notification
{
    use Queueable;

    public $rental;

    /**
     * Create a new notification instance.
     */
    public function __construct($rental)
    {
        $this->rental = $rental;
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
        $statusMap = [
            'ready_pickup' => 'Siap Diambil',
            'delivering' => 'Dalam Pengiriman',
            'rented' => 'Sedang Disewa',
            'returned' => 'Dikembalikan',
            'cancelled' => 'Dibatalkan',
        ];

        $statusText = $statusMap[$this->rental->status] ?? $this->rental->status;
        $message = 'Status pesanan #' . $this->rental->id . ' Anda telah diperbarui menjadi: ' . $statusText . '.';

        if ($this->rental->status === 'ready_pickup') {
            $message = 'Barang untuk pesanan #' . $this->rental->id . ' sudah siap diambil!';
        }

        return [
            'type' => 'rental_status_updated',
            'title' => 'Pembaruan Pesanan',
            'message' => $message,
            'rental_id' => $this->rental->id,
            'status' => $this->rental->status,
        ];
    }
}
