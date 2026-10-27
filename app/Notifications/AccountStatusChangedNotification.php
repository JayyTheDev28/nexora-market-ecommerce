<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $status,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = match ($this->status) {
            'active' => 'Activated',
            'suspended' => 'Suspended',
            'deactivated' => 'Deactivated',
            default => ucfirst($this->status),
        };

        $mail = (new MailMessage)
            ->subject("Your Nexora Market account has been {$statusLabel}")
            ->greeting("Hello, {$notifiable->first_name}!");

        if ($this->status === 'active') {
            $mail->line('Your account has been activated and you now have full access to the Nexora Market platform.')
                ->action('Log in to Nexora Market', url('/login'))
                ->line('Thank you for being part of our marketplace.');
        } elseif ($this->status === 'suspended') {
            $mail->line('Your account has been temporarily suspended by the platform administrator.')
                ->lineIf((bool) $this->reason, "Reason: {$this->reason}")
                ->line('While suspended, you cannot access platform services or conduct transactions.')
                ->line('If you believe this is in error, please reach out to Nexora Market Support.');
        } else {
            $mail->line('Your account has been deactivated.')
                ->lineIf((bool) $this->reason, "Reason: {$this->reason}")
                ->line('If you have any questions or require assistance, please contact Nexora Market Support.');
        }

        return $mail;
    }
}
