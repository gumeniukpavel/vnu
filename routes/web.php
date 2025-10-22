<?php

use App\Http\Controllers\Api\CoursesController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Ms365Controller;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\MicrosoftController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

if (app()->environment('local')) {
    Route::get('/dev/login', [DevLoginController::class,'login']);
}

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => redirect()->route('login.microsoft'))->name('login');
    Route::get('/login/microsoft', [MicrosoftController::class, 'redirect'])
        ->name('login.microsoft');
    Route::get('/login/microsoft/callback', [MicrosoftController::class, 'callback'])
        ->name('login.microsoft.callback');
});

Route::post('/logout', [DevLoginController::class,'logout'])->middleware('auth');

Route::prefix('api')->middleware(['auth'])->group(function () {
    Route::get('/me', [MeController::class,'__invoke']);
    Route::get('/schedule', [ScheduleController::class,'index']);
    Route::get('/courses', [CoursesController::class,'index']);
    Route::get('/courses/{id}/assignments', [CoursesController::class,'assignments']);
    Route::get('/notifications', [NotificationsController::class,'index']);

    Route::get('/ms365/me', [Ms365Controller::class,'me']);
    Route::get('/ms365/photo', [Ms365Controller::class,'photo']);
    Route::get('/ms365/calendar/today', [Ms365Controller::class,'calendarToday']);

    Route::get('/ms365/mail/unread', [Ms365Controller::class,'mailUnread']);
    Route::get('/ms365/mail/top', [Ms365Controller::class,'mailTop']);

    Route::get('/ms365/mail/{id}', [Ms365Controller::class,'mailShow']);
    Route::get('/ms365/mail/{id}/attachments', [Ms365Controller::class,'mailAttachments']);
    Route::get('/ms365/mail/{id}/attachments/{attId}/download', [Ms365Controller::class,'mailAttachmentDownload']);

    Route::get('/ms365/drive/recent', [Ms365Controller::class,'driveRecent']);
    Route::get('/ms365/drive/items/{id}', [Ms365Controller::class,'driveItem']);
    Route::get('/ms365/drive/items/{id}/download', [Ms365Controller::class,'driveDownload']);
});
