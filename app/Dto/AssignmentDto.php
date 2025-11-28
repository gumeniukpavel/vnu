<?php

namespace App\Dto;

final class AssignmentDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $courseId,
        public readonly string $title,
        public readonly ?string $dueAt = null,
        public readonly string $status = 'pending',
        public readonly ?string $intro = null,
        public readonly ?string $allowSubmissionsFromDate = null,
        public readonly bool $submitted = false,
        public readonly ?string $courseName = null,
        public readonly ?string $source = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'course_id' => $this->courseId,
            'title' => $this->title,
            'due_at' => $this->dueAt,
            'status' => $this->status,
            'intro' => $this->intro,
            'allowsubmissionsfromdate' => $this->allowSubmissionsFromDate,
            'submitted' => $this->submitted,
            'course_name' => $this->courseName,
            'source' => $this->source,
        ], fn($value) => $value !== null);
    }
}



