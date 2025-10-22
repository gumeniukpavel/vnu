<?php

namespace App\Integrations\Contracts;

interface ScheduleProvider {
    /** @return array<array{start:string,end:string,title:string,location?:string,group?:string,source:string}> */
    public function fetch(\DateTimeInterface $from, \DateTimeInterface $to, string $group): array;
}
