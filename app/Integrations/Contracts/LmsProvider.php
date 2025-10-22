<?php
namespace App\Integrations\Contracts;

interface LmsProvider {
    /** @return array<array{id:int,code:string,title:string,role?:string}> */
    public function coursesForUser(int $userId): array;

    /** @return array<array{id:int,course_id:int,title:string,due_at?:string,status:string}> */
    public function assignmentsForUser(int $userId): array;
}
