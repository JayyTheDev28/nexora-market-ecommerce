<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Dispute $dispute,
        public string $recipientType = 'buyer' // 'buyer' or 'seller'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = ucfirst($this->dispute->status);
        $order = $this->dispute->order;

        $mail = (new MailMessage)
            ->subject("Update on Order #{$order->order_number} Dispute — Nexora Market")
            ->greeting("Hello, {$notifiable->first_name}!");

        if ($this->dispute->status === 'resolved') {
            $mail->line("The dispute regarding Order #{$order->order_number} has been resolved by our mediation team.")
                ->line("Decision: Resolved")
                ->lineIf((bool) $this->dispute->resolution_note, "Resolution Note: {$this->dispute->resolution_note}");
        } elseif ($this->dispute->status === 'rejected') {
            $mail->line("The dispute claim for Order #{$order->order_number} has been reviewed and declined.")
                ->line("Decision: Rejected")
                ->lineIf((bool) $this->dispute->resolution_note, "Reason: {$this->dispute->resolution_note}");
        } else {
            $mail->line("The dispute regarding Order #{$order->order_number} is currently under investigation by our arbitration team.");
        }

        $mail->action('View Order Details', url("/buyer/orders"))
            ->line('Thank you for your cooperation.');

        return $mail;
    }
}
