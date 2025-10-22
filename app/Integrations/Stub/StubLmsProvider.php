<?php
namespace App\Integrations\Stub;

use App\Integrations\Contracts\LmsProvider;

final class StubLmsProvider implements LmsProvider {
    public function coursesForUser(int $userId): array {
        return [
            ['id'=>101,'code'=>'CS-101','title'=>'Алгоритми','role'=>'student'],
            ['id'=>202,'code'=>'OS-202','title'=>'Операційні системи','role'=>'student'],
        ];
    }
    public function assignmentsForUser(int $userId): array {
        return [
            ['id'=>1,'course_id'=>101,'title'=>'Д/з №1: сортування','due_at'=>now()->addDays(3)->toIso8601String(),'status'=>'pending'],
            ['id'=>2,'course_id'=>202,'title'=>'Лаб №2: процеси','due_at'=>now()->addDays(5)->toIso8601String(),'status'=>'pending'],
        ];
    }
}
