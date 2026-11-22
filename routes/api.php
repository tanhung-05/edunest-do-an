<?php

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Support\Facades\Route;

/*
| P8 M28: REST API v1 cho ứng dụng di động (Sanctum, token gắn thiết bị). Tài liệu: docs/thiet-ke/api-v1.md.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/tokens', [ApiController::class, 'token'])->middleware('throttle:10,1')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'api.device', 'throttle:120,1'])->group(function () {
        Route::delete('/tokens/current', [ApiController::class, 'logout'])->name('tokens.destroy');
        Route::get('/me', [ApiController::class, 'me'])->name('me');
        Route::get('/courses', [ApiController::class, 'courses'])->name('courses.index');
        Route::get('/courses/{course}/lessons', [ApiController::class, 'lessons'])->name('courses.lessons.index');
        Route::get('/courses/{course}/lessons/{lesson}', [ApiController::class, 'lesson'])->name('courses.lessons.show');
        Route::post('/courses/{course}/lessons/{lesson}/complete', [ApiController::class, 'complete'])->name('courses.lessons.complete');
        Route::get('/notifications', [ApiController::class, 'notifications'])->name('notifications.index');
    });
});
