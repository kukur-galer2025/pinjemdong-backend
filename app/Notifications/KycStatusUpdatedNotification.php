<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycStatusUpdatedNotification extends Notification
{
    use Queueable;

    public $status;
    public $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct($status, $reason = null)
    {
        $this->status = $status;
        $this->reason = $reason;
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
        $isVerified = $this->status === 'verified';
        $title = $isVerified ? 'Verifikasi KYC Berhasil' : 'Verifikasi KYC Ditolak';
        $message = $isVerified 
            ? 'Selamat! Dokumen KYC Anda telah diverifikasi. Anda sekarang dapat melakukan penyewaan barang.' 
            : 'Mohon maaf, dokumen KYC Anda ditolak. Alasan: ' . ($this->reason ?? 'Tidak sesuai ketentuan.');

        return [
            'type' => 'kyc_status_updated',
            'title' => $title,
            'message' => $message,
            'status' => $this->status,
        ];
    }
}
