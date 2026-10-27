<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiderActivationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = route('rider.activate', ['token' => $this->token]);

        return (new MailMessage)
            ->subject('Activate Your Nexora Delivery Rider Account')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your logistics company has created a courier / rider account for you on Nexora Market.')
            ->line('To activate your account and set up your login password, click the button below:')
            ->action('Activate Account & Set Password', $activationUrl)
            ->line('Security Notice: This activation link will expire in 24 hours and can only be used once.')
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
