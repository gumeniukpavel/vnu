<?php

namespace App\Repositories;

use App\Models\User;

final class UserRepository
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updateAzureTokens(int $userId, string $accessToken, string $refreshToken, \DateTimeInterface $expiresAt): bool
    {
        return User::where('id', $userId)->update([
            'azure_access_token' => $accessToken,
            'azure_refresh_token' => $refreshToken,
            'azure_token_expires_at' => $expiresAt,
        ]) > 0;
    }
}

