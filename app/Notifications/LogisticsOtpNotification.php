<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class LogisticsOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp,
        public string $companyName,
        public string $contactName
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nexora Market — Logistics Partner Verification Code: ' . $this->otp)
            ->greeting("Hello {$this->contactName},")
            ->line("Thank you for registering {$this->companyName} as an official Logistics Partner on Nexora Market.")
            ->line('Please use the 6-digit verification code below to verify your email address and submit your application for compliance review:')
            ->line(new HtmlString('<div style="text-align: center; margin: 24px 0;"><div style="display: inline-block; padding: 14px 32px; background-color: #f0f4ff; border: 2px dashed #0058be; border-radius: 12px;"><span style="font-family: \'Courier New\', Courier, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #0058be;">' . e($this->otp) . '</span></div></div>'))
            ->line('This verification code expires in 10 minutes. For your security, do not share this code with anyone.')
            ->line('If you or your company did not apply for a Logistics Partner account on Nexora Market, please disregard this email.')
            ->salutation("Best regards,\nThe Nexora Market Team");
    }
}

