<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{MeController,
    Ms365Controller,
    ScheduleController,
    CoursesController,
    NotificationsController};
use App\Http\Controllers\Auth\DevTokenController;

Route::post('/dev/token', [DevTokenController::class, 'issue']);

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/me', [MeController::class,'__invoke']);
    Route::get('/schedule', [ScheduleController::class,'index']);
    Route::get('/courses', [CoursesController::class,'index']);
    Route::get('/courses/{id}/assignments', [CoursesController::class,'assignments']);
    Route::get('/notifications', [NotificationsController::class,'index']);
    Route::get('/ms365/me', [Ms365Controller::class, 'me']);
    Route::get('/ms365/calendar/today', [Ms365Controller::class, 'calendarToday']);
});
