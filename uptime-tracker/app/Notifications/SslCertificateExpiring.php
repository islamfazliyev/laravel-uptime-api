<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SslCertificateExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor, public int $daysRemaining)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject("SSL Sertifika Uyarısı: {$this->monitor->url}")
                    ->greeting('Merhaba!')
                    ->line("{$this->monitor->url} adresi için SSL sertifikası {$this->daysRemaining} gün içinde dolacak.")
                    ->line("Bitiş Tarihi: " . $this->monitor->certificate_expiration_date->format('Y-m-d H:i:s'))
                    ->action('Kontrol Paneli', url('/'))
                    ->line('Lütfen en kısa sürede sertifikanızı yenileyin.');
    }
}
