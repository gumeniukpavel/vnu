<?php

namespace App\Services;

use App\Dto\ScheduleEventDto;
use App\Integrations\Contracts\ScheduleProvider;
use Illuminate\Support\Facades\Cache;

final class ScheduleService
{
    public function __construct(
        private ScheduleProvider $scheduleProvider
    ) {
    }

    public function getScheduleEvents(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $group = 'IPZ-11'
    ): array {
        $cacheKey = "schedule:{$group}:" . $from->format('Y-m-d') . ':' . $to->format('Y-m-d');
        
        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to, $group) {
            return $this->scheduleProvider->fetch($from, $to, $group);
        });

        return array_map(function ($event) {
            return new ScheduleEventDto(
                title: $event['title'] ?? '',
                start: $event['start'] ?? '',
                end: $event['end'] ?? '',
                location: $event['location'] ?? null,
                group: $event['group'] ?? null,
                teacher: $event['teacher'] ?? null,
            );
        }, $data);
    }

    public function getScheduleEventsAsArray(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $group = 'IPZ-11'
    ): array {
        return array_map(
            fn(ScheduleEventDto $dto) => $dto->toArray(),
            $this->getScheduleEvents($from, $to, $group)
        );
    }

    public function parseDate(string $dateParam, string $default = 'now'): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($dateParam);
        } catch (\Exception $e) {
            return new \DateTimeImmutable($default);
        }
    }
}



