<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmailVerificationOtpNotification extends Notification
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
            ->subject('Your Nexora Market Verification Code: ' . $this->otp)
            ->greeting("Hello {$name},")
            ->line('Thank you for registering with Nexora Market. Please enter the 6-digit verification code below to verify your email address:')
            ->line(new HtmlString('<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" style="margin: 24px auto; text-align: center;"><tr><td align="center" style="background-color: #f0f4ff; border: 2px dashed #0058be; border-radius: 12px; padding: 14px 32px;"><span style="font-family: \'Courier New\', Courier, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #0058be; display: inline-block;">' . e($this->otp) . '</span></td></tr></table>'))
            ->line('This code will expire in 15 minutes. For your security, do not share this code with anyone.')
            ->line('If you did not create an account on Nexora Market, please disregard this email.')
            ->salutation("Best regards,\nThe Nexora Market Team");
    }
}
