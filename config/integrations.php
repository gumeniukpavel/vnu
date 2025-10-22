<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integration Drivers
    |--------------------------------------------------------------------------
    |
    | Тут задаються драйвери для інтеграцій. Зараз стоять "stub" —
    | вони повертають тестові дані. Потім ти зможеш підставити "moodle",
    | "ics", "koha" тощо без зміни коду контролерів.
    |
    */

    'schedule' => env('INTEGRATION_SCHEDULE_DRIVER', 'stub'),
    'lms'      => env('INTEGRATION_LMS_DRIVER', 'stub'),
    'library'  => env('INTEGRATION_LIBRARY_DRIVER', 'stub'),
    'repository' => env('INTEGRATION_REPOSITORY_DRIVER', 'stub'),
];
