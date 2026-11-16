<?php

namespace App\Notifications;

use App\Models\ParcelIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParcelIncidentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ParcelIncident $incident,
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
            'incident_id' => $this->incident->id,
            'incident_number' => $this->incident->incident_number,
            'incident_type' => $this->incident->incident_type,
            'investigation_status' => $this->incident->investigation_status,
            'disposition' => $this->incident->disposition,
            'handling_restriction' => $this->incident->handling_restriction,
            'tracking_number' => $this->incident->tracking_number,
            'order_id' => $this->incident->order_id,
            'manifest_id' => $this->incident->manifest_id,
            'hub_id' => $this->incident->hub_id,
        ], $this->extra);
    }
}
