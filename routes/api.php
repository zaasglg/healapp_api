<?php

use App\Http\Controllers\Api\v1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
        Route::post('/login', [AuthController::class, 'login']);
        
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::patch('/profile', [AuthController::class, 'updateProfile']);
            Route::post('/change-phone/request', [AuthController::class, 'changePhoneRequest']);
            Route::post('/change-phone/confirm', [AuthController::class, 'confirmChangePhone']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('patients', \App\Http\Controllers\Api\v1\PatientController::class);
        Route::get('/diary', [\App\Http\Controllers\Api\v1\DiaryController::class, 'index']);
        Route::post('/diary', [\App\Http\Controllers\Api\v1\DiaryController::class, 'store']);
        
        Route::get('/task-templates', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'index']);
        Route::post('/task-templates', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'store']);
        Route::delete('/task-templates/{taskTemplate}', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'destroy']);
        
        Route::get('/tasks', [\App\Http\Controllers\Api\v1\TaskController::class, 'index']);
        Route::patch('/tasks/{task}/status', [\App\Http\Controllers\Api\v1\TaskController::class, 'updateStatus']);
        
        Route::prefix('organization')->group(function () {
            Route::get('/employees', [\App\Http\Controllers\Api\v1\OrganizationController::class, 'getEmployees']);
            Route::post('/invite-employee', [\App\Http\Controllers\Api\v1\OrganizationController::class, 'inviteEmployee']);
            Route::post('/assign-patient', [\App\Http\Controllers\Api\v1\OrganizationController::class, 'assignPatient']);
            Route::patch('/', [\App\Http\Controllers\Api\v1\OrganizationController::class, 'update']);
        });
        
        Route::get('/notifications', [\App\Http\Controllers\Api\v1\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\v1\NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\v1\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\v1\NotificationController::class, 'markAllRead']);
        
        Route::get('/stats/chart', [\App\Http\Controllers\Api\v1\StatsController::class, 'getDiaryChart']);
        Route::get('/stats/tasks', [\App\Http\Controllers\Api\v1\StatsController::class, 'getTaskSummary']);
    });
});

