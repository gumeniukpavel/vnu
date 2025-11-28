<?php

namespace App\Dto;

final class UserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $givenName = null,
        public readonly ?string $surname = null,
        public readonly ?string $jobTitle = null,
        public readonly ?string $department = null,
        public readonly ?string $phone = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'given_name' => $this->givenName,
            'surname' => $this->surname,
            'job_title' => $this->jobTitle,
            'department' => $this->department,
            'phone' => $this->phone,
        ];
    }
}



