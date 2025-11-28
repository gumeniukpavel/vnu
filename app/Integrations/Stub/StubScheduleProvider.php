<?php
namespace App\Integrations\Stub;

use App\Integrations\Contracts\ScheduleProvider;

final class StubScheduleProvider implements ScheduleProvider {
    public function fetch(\DateTimeInterface $from, \DateTimeInterface $to, string $group): array {
        $result = [];
        $currentDate = \DateTimeImmutable::createFromInterface($from);
        
        while ($currentDate <= $to) {
            $dayStart = $currentDate->setTime(0, 0, 0);
            
            $daySchedule = [
                [
                    'start' => (clone $dayStart)->setTime(8, 30)->format('c'),
                    'end' => (clone $dayStart)->setTime(10, 0)->format('c'),
                    'title' => 'Алгоритми та структури даних',
                    'location' => '301',
                    'group' => $group,
                    'source' => 'stub'
                ],
                [
                    'start' => (clone $dayStart)->setTime(10, 15)->format('c'),
                    'end' => (clone $dayStart)->setTime(11, 45)->format('c'),
                    'title' => 'Бази даних',
                    'location' => '214',
                    'group' => $group,
                    'source' => 'stub'
                ],
                [
                    'start' => (clone $dayStart)->setTime(12, 30)->format('c'),
                    'end' => (clone $dayStart)->setTime(14, 0)->format('c'),
                    'title' => 'Веб-програмування',
                    'location' => '405',
                    'group' => $group,
                    'source' => 'stub'
                ],
                [
                    'start' => (clone $dayStart)->setTime(14, 15)->format('c'),
                    'end' => (clone $dayStart)->setTime(15, 45)->format('c'),
                    'title' => 'Операційні системи',
                    'location' => '302',
                    'group' => $group,
                    'source' => 'stub'
                ],
                [
                    'start' => (clone $dayStart)->setTime(16, 0)->format('c'),
                    'end' => (clone $dayStart)->setTime(17, 30)->format('c'),
                    'title' => 'Комп\'ютерні мережі',
                    'location' => '201',
                    'group' => $group,
                    'source' => 'stub'
                ],
            ];
            
            $result = array_merge($result, $daySchedule);
            $currentDate = $currentDate->modify('+1 day');
        }
        
        return $result;
    }
}
