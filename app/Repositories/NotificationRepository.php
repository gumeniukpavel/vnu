<?php

namespace App\Repositories;

use App\Models\NotificationItem;
use Illuminate\Support\Collection;

final class NotificationRepository
{
    public function findByUserId(int $userId, int $limit = 20): Collection
    {
        return NotificationItem::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): NotificationItem
    {
        return NotificationItem::create($data);
    }
}



