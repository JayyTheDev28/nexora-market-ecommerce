<?php

namespace App\Notifications;

use App\Models\SellerWarning;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SellerWarning $warning
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = ucwords(str_replace('_', ' ', $this->warning->violation_type));

        $mail = (new MailMessage)
            ->subject('Important Notice: Seller Compliance Warning — Nexora Market')
            ->greeting("Hello, {$notifiable->first_name}!")
            ->line('A compliance warning has been issued regarding your seller account on Nexora Market.')
            ->line("Violation Category: {$typeLabel}");

        if ($this->warning->product) {
            $mail->line("Affected Listing: {$this->warning->product->name}");
        }

        $mail->line("Details: {$this->warning->details}")
            ->line('Please review your listings and business practices to ensure compliance with Nexora Market merchant guidelines.')
            ->line('Repeated infractions may result in temporary or permanent suspension of your seller privileges.')
            ->action('Go to Seller Dashboard', url('/seller/dashboard'));

        return $mail;
    }
}
