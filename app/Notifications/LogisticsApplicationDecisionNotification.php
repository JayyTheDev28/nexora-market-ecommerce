<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LogisticsApplicationDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public bool $approved,
        public ?string $reason = null,
        public ?string $companyName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = $this->companyName ?? 'Your Company';
        $name = $notifiable->first_name ?? ($notifiable->contact_person_first_name ?? 'Partner');

        if ($this->approved) {
            return (new MailMessage)
                ->subject('Welcome to Nexora Market — Logistics Partner Application Approved!')
                ->greeting("Hello {$name},")
                ->line("Congratulations! The logistics partner application for {$company} has been approved by the platform administration.")
                ->line('Your account is now active. You may log in to access the Logistics Operations & Hub Dispatch Dashboard, onboard delivery fleet riders, and manage regional parcel handoffs.')
                ->action('Go to Logistics Dashboard', url('/logistics/dashboard'))
                ->salutation("Best regards,\nThe Nexora Market Team");
        }

        return (new MailMessage)
            ->subject('Update Regarding Your Nexora Market Logistics Partner Application')
            ->greeting("Hello {$name},")
            ->line("Thank you for your interest in partnering with Nexora Market. After reviewing the application submitted for {$company}, our compliance team was unable to approve it at this time.")
            ->line('Reason for decision:')
            ->line("“{$this->reason}”")
            ->line('You may log in to your account, update your business information or re-upload the requested documents, and resubmit for reconsideration.')
            ->action('Review Feedback & Resubmit', url('/logistics/resubmit'))
            ->salutation("Best regards,\nThe Nexora Market Compliance Team");
    }
}

