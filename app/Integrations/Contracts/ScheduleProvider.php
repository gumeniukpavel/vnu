<?php

namespace App\Integrations\Contracts;

interface ScheduleProvider {
    public function fetch(\DateTimeInterface $from, \DateTimeInterface $to, string $group): array;
}
