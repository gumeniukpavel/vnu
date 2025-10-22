<?php
namespace App\Integrations\Stub;

use App\Integrations\Contracts\ScheduleProvider;

final class StubScheduleProvider implements ScheduleProvider {
    public function fetch(\DateTimeInterface $from, \DateTimeInterface $to, string $group): array {
        return [
            ['start'=> $from->format('c'), 'end'=> (clone $from)->modify('+90 minutes')->format('c'),
                'title'=>'Алгоритми та складність', 'location'=>'ауд. 301', 'group'=>$group, 'source'=>'stub'],
            ['start'=> (clone $from)->modify('+3 hours')->format('c'),'end'=> (clone $from)->modify('+4 hours 30 minutes')->format('c'),
                'title'=>'Операційні системи','location'=>'ауд. 214','group'=>$group,'source'=>'stub'],
        ];
    }
}
