<?php

namespace App\Dto;

final class NotificationDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly ?string $content = null,
        public readonly ?string $type = null,
        public readonly ?string $createdAt = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'created_at' => $this->createdAt,
        ], fn($value) => $value !== null);
    }
}



