<?php

namespace App\Dto;

final class ScheduleEventDto
{
    public function __construct(
        public readonly string $title,
        public readonly string $start,
        public readonly string $end,
        public readonly ?string $location = null,
        public readonly ?string $group = null,
        public readonly ?string $teacher = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'start' => $this->start,
            'end' => $this->end,
            'location' => $this->location,
            'group' => $this->group,
            'teacher' => $this->teacher,
        ], fn($value) => $value !== null);
    }
}



