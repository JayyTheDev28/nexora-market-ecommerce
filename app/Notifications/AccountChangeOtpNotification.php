<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AccountChangeOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp,
        public string $type = 'email' // 'email' or 'phone'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->first_name ?? 'there';
        $item = $this->type === 'email' ? 'email address' : 'mobile phone number';

        return (new MailMessage)
            ->subject("Your Nexora Market Security Code: {$this->otp}")
            ->greeting("Hello {$name},")
            ->line("You requested to update your account's {$item}. Please use the 6-digit confirmation code below to verify this change:")
            ->line(new HtmlString('<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" style="margin: 24px auto; text-align: center;"><tr><td align="center" style="background-color: #f0f4ff; border: 2px dashed #0058be; border-radius: 12px; padding: 14px 32px;"><span style="font-family: \'Courier New\', Courier, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #0058be; display: inline-block;">' . e($this->otp) . '</span></td></tr></table>'))
            ->line('This code will expire in 15 minutes. If you did not request this update, please change your password immediately.')
            ->salutation("Best regards,\nThe Nexora Market Team");
    }
}
