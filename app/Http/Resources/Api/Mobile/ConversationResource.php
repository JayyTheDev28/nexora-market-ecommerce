<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id ?? auth('sanctum')->id() ?? auth()->id();
        $participants = $this->relationLoaded('participants') ? $this->participants : $this->participants()->get();
        $otherParticipant = $userId ? $participants->firstWhere('id', '!=', $userId) : $participants->first();
        $lastMessage = $this->relationLoaded('messages') ? $this->messages->first() : $this->messages()->latest()->first();

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'other_participant' => $otherParticipant ? [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->sellerProfile?->business_name ?? $otherParticipant->full_name,
                'avatar_url' => $otherParticipant->avatar_url,
                'role' => $otherParticipant->role,
            ] : null,
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'sender_id' => $lastMessage->sender_id,
                'body' => $lastMessage->body,
                'created_at' => $lastMessage->created_at?->diffForHumans(),
            ] : null,
            'last_message_at' => $this->last_message_at?->format('Y-m-d H:i:s'),
        ];
    }
}

