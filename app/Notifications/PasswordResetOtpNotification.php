<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->first_name ?? 'there';

        return (new MailMessage)
            ->subject('Your Nexora Market Password Reset Code: ' . $this->otp)
            ->greeting("Hello {$name},")
            ->line('We received a request to reset the password for your Nexora Market account. Enter this 6-digit security code to continue:')
            ->line(new HtmlString('<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" style="margin: 24px auto; text-align: center;"><tr><td align="center" style="background-color: #f0f4ff; border: 2px dashed #0058be; border-radius: 12px; padding: 14px 32px;"><span style="font-family: \'Courier New\', Courier, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #0058be; display: inline-block;">' . e($this->otp) . '</span></td></tr></table>'))
            ->line('This code expires in 15 minutes. Never share this code with anyone.')
            ->line('If you did not request a password reset, you can safely ignore this email.')
            ->salutation("Best regards,\nThe Nexora Market Team");
    }
}
