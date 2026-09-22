<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationDecision extends Notification
{
    use Queueable;

    public function __construct(
        public bool $approved,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject('Your Nexora Market application has been approved')
                ->greeting("Good news, {$notifiable->first_name}!")
                ->line("Your application to join Nexora Market as a {$notifiable->roleLabel()} has been approved.")
                ->action('Sign In to Nexora', url('/login'))
                ->line('Welcome to Nexora Market.');
        }

        return (new MailMessage)
            ->subject('Update on your Nexora Market application')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('After reviewing your application to join Nexora Market, we were unable to approve it at this time.')
            ->lineIf((bool) $this->reason, "Reason: {$this->reason}")
            ->line('If you believe this was a mistake, you may reply to this email or submit a new application with updated documents.');
    }
}
