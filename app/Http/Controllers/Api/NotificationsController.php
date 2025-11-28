<?php
namespace App\Http\Controllers\Api;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class NotificationsController
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getNotificationsForUserAsArray($request->user()->id);
        return response()->json($notifications);
    }

    public function news(Request $request): JsonResponse
    {
        $news = $this->notificationService->getNews();
        return response()->json($news);
    }
}
