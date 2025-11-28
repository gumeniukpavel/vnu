<?php

namespace App\Dto;

final class MoodleCourseDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $fullname,
        public readonly ?string $shortname = null,
        public readonly ?string $summary = null,
        public readonly ?int $summaryFormat = null,
        public readonly ?string $image = null,
        public readonly ?string $courseUrl = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $startDate = null,
        public readonly ?int $endDate = null,
        public readonly int $visible = 1,
        public readonly array $assignments = [],
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'fullname' => $this->fullname,
            'shortname' => $this->shortname,
            'summary' => $this->summary,
            'summaryformat' => $this->summaryFormat,
            'image' => $this->image,
            'courseUrl' => $this->courseUrl,
            'categoryid' => $this->categoryId,
            'startdate' => $this->startDate,
            'enddate' => $this->endDate,
            'visible' => $this->visible,
            'assignments' => $this->assignments,
        ], fn($value) => $value !== null && $value !== []);
    }
}



