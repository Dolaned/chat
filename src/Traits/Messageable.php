<?php

namespace Musonza\Chat\Traits;

use Musonza\Chat\Models\Participation;

trait Messageable
{
    public function conversations()
    {
        return $this->participation->pluck('conversation');
    }

    public function participation()
    {
        return $this->morphMany(Participation::class, 'messageable');
    }

    public function joinConversation($conversationId)
    {
        $participation = new Participation([
            'messageable_id'   => $this->getKey(),
            'messageable_type' => get_class($this),
            'conversation_id'  => $conversationId,
        ]);

        $this->participation()->save($participation);
    }

    public function leaveConversation($conversationId)
    {
        $this->participation()->where([
            'messageable_id'   => $this->getKey(),
            'messageable_type' => get_class($this),
            'conversation_id'  => $conversationId,
        ])->delete();
    }

    public function getParticipantDetails(): array
    {
        return $this->participantDetails ?? ['name' => $this->name ?? null];
    }
}
