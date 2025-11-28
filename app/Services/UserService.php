<?php

namespace App\Services;

use App\Dto\UserDto;
use App\Repositories\UserRepository;

final class UserService
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public function getUserDto(int $userId): ?UserDto
    {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            return null;
        }

        return new UserDto(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            givenName: $user->given_name ?? null,
            surname: $user->surname ?? null,
            jobTitle: $user->job_title ?? null,
            department: $user->department ?? null,
            phone: $user->phone ?? null,
        );
    }

    public function getUserByEmail(string $email): ?UserDto
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            return null;
        }

        return new UserDto(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            givenName: $user->given_name ?? null,
            surname: $user->surname ?? null,
            jobTitle: $user->job_title ?? null,
            department: $user->department ?? null,
            phone: $user->phone ?? null,
        );
    }
}



