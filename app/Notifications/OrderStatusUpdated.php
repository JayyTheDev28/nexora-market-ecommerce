<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    use Queueable;

    private const STATUS_LABELS = [
        'confirmed' => 'Your order has been accepted by the seller',
        'preparing' => 'Your order is being prepared',
        'ready_for_pickup' => 'Your order is packed and ready for pickup',
        'picked_up' => 'Your order has been picked up by a rider',
        'at_sorting_center' => 'Your order has arrived at the sorting center',
        'sorted' => 'Your order has been sorted for delivery',
        'assigned_to_rider' => 'A delivery rider has been assigned to your order',
        'out_for_delivery' => 'Your order is out for delivery',
        'delivered' => 'Your order has been delivered',
        'completed' => 'Your order is complete',
        'delivery_failed' => 'A delivery attempt for your order failed',
        'returned' => 'Your order has been returned to the seller',
    ];

    public function __construct(public Order $order, public string $status) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $headline = self::STATUS_LABELS[$this->status] ?? 'Your order status has been updated';

        return (new MailMessage)
            ->subject("Order {$this->order->order_number}: " . $headline)
            ->greeting("Hi {$notifiable->first_name},")
            ->line($headline . '.')
            ->line("Order number: {$this->order->order_number}")
            ->action('Track My Order', url('/buyer/orders'));
    }
}
