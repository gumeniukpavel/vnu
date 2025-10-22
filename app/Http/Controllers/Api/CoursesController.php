<?php
namespace App\Http\Controllers\Api;

use App\Integrations\Contracts\LmsProvider;
use Illuminate\Http\Request;

final class CoursesController
{
    public function __construct(private LmsProvider $lms) {}

    public function index(Request $r)
    {
        return response()->json($this->lms->coursesForUser($r->user()->id));
    }

    public function assignments(Request $r, int $id)
    {
        // у заглушці повертаємо всі; з реальним провайдером — фільтруємо по course_id
        return response()->json(
            array_values(array_filter(
                $this->lms->assignmentsForUser($r->user()->id),
                fn($a) => (int)$a['course_id'] === (int)$id
            ))
        );
    }
}
