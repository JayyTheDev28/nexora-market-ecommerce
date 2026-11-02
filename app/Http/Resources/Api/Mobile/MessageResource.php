<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender?->sellerProfile?->business_name ?? $this->sender?->full_name,
            'sender_avatar' => $this->sender?->avatar_url,
            'is_me' => $this->sender_id === $request->user()?->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}

