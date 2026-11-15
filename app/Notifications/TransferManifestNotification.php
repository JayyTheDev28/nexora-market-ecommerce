<?php

namespace App\Notifications;

use App\Models\TransferManifest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferManifestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TransferManifest $manifest,
        public string $event,
        public string $title,
        public string $message,
        public array $extra = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'manifest_id' => $this->manifest->id,
            'manifest_number' => $this->manifest->manifest_number,
            'status' => $this->manifest->status,
            'origin_hub_id' => $this->manifest->origin_hub_id,
            'destination_hub_id' => $this->manifest->destination_hub_id,
        ], $this->extra);
    }
}
