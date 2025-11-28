<?php
namespace App\Http\Controllers\Api;

use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class ScheduleController
{
    public function __construct(
        private ScheduleService $scheduleService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $fromParam = $request->query('from', 'now');
        $toParam = $request->query('to', '+1 day');
        $group = $request->query('group', 'IPZ-11');
        
        $from = $this->scheduleService->parseDate($fromParam, 'now');
        $to = $this->scheduleService->parseDate($toParam, '+1 day');
        
        $events = $this->scheduleService->getScheduleEventsAsArray($from, $to, $group);
        
        return response()->json(['data' => $events]);
    }
}
