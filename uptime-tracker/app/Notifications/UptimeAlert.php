<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UptimeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Monitor $monitor,
        public bool $isUp,
        public ?int $statusCode
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail']; // Discord can be added here or kept separate
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusStr = $this->isUp ? '✅ ONLINE' : '🚨 OFFLINE';
        $message = (new MailMessage)
                    ->subject("Monitor Alert [{$statusStr}]: {$this->monitor->url}")
                    ->greeting("Monitor Status Changed");

        if (!$this->isUp) {
            $message->line("{$this->monitor->url} is currently down.");
            if ($this->statusCode) {
                $message->line("Status Code: {$this->statusCode}");
            } else {
                $message->line("Reason: Connection timeout or unreachable.");
            }
        } else {
            $message->line("{$this->monitor->url} is back online.");
        }

        return $message
                ->action('View Monitor', url('/'))
                ->line('Thank you for using Uptime Tracker!');
    }
}
