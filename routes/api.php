<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\InvitationController;
use App\Http\Controllers\Api\v1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    
    // ========================================
    // AUTH (публичные)
    // ========================================
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

    // ========================================
    // INVITATIONS (публичные)
    // ========================================
    Route::prefix('invitations')->group(function () {
        // Публичный просмотр приглашения
        Route::get('/{token}', [InvitationController::class, 'show']);
        // Принятие приглашения (регистрация/привязка)
        Route::post('/{token}/accept', [InvitationController::class, 'accept']);
    });

    // ========================================
    // AUTHENTICATED ROUTES
    // ========================================
    Route::middleware('auth:sanctum')->group(function () {
        
        // ========================================
        // PATIENTS
        // ========================================
        Route::apiResource('patients', \App\Http\Controllers\Api\v1\PatientController::class);
        
        // ========================================
        // DIARY
        // ========================================
        Route::get('/diary', [\App\Http\Controllers\Api\v1\DiaryController::class, 'index']);
        Route::get('/diary/{id}', [\App\Http\Controllers\Api\v1\DiaryController::class, 'show']);
        Route::post('/diary', [\App\Http\Controllers\Api\v1\DiaryController::class, 'store']);
        Route::post('/diary/create', [\App\Http\Controllers\Api\v1\DiaryController::class, 'create']);
        Route::patch('/diary/pinned', [\App\Http\Controllers\Api\v1\DiaryController::class, 'updatePinned']);
        
        // ========================================
        // ALARMS
        // ========================================
        Route::get('/alarms', [\App\Http\Controllers\Api\v1\AlarmController::class, 'index']);
        Route::get('/alarms/{id}', [\App\Http\Controllers\Api\v1\AlarmController::class, 'show']);
        Route::post('/alarms', [\App\Http\Controllers\Api\v1\AlarmController::class, 'store']);
        Route::put('/alarms/{id}', [\App\Http\Controllers\Api\v1\AlarmController::class, 'update']);
        Route::delete('/alarms/{id}', [\App\Http\Controllers\Api\v1\AlarmController::class, 'destroy']);
        Route::patch('/alarms/{id}/toggle', [\App\Http\Controllers\Api\v1\AlarmController::class, 'toggle']);
        
        // ========================================
        // TASK TEMPLATES
        // ========================================
        Route::get('/task-templates', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'index']);
        Route::get('/task-templates/{taskTemplate}', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'show']);
        Route::post('/task-templates', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'store']);
        Route::put('/task-templates/{taskTemplate}', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'update']);
        Route::patch('/task-templates/{taskTemplate}/toggle', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'toggle']);
        Route::delete('/task-templates/{taskTemplate}', [\App\Http\Controllers\Api\v1\TaskTemplateController::class, 'destroy']);
        
        // ========================================
        // TASKS
        // ========================================
        Route::get('/tasks', [\App\Http\Controllers\Api\v1\TaskController::class, 'index']);
        Route::patch('/tasks/{task}/status', [\App\Http\Controllers\Api\v1\TaskController::class, 'updateStatus']);
        
        // ========================================
        // ORGANIZATION
        // ========================================
        Route::prefix('organization')->group(function () {
            Route::get('/', [OrganizationController::class, 'show']);
            Route::patch('/', [OrganizationController::class, 'update']);
            
            // Employees
            Route::get('/employees', [OrganizationController::class, 'getEmployees']);
            Route::patch('/employees/{id}/role', [OrganizationController::class, 'changeEmployeeRole']);
            Route::delete('/employees/{id}', [OrganizationController::class, 'removeEmployee']);
            
            // Diary access (для агентств)
            Route::post('/assign-diary-access', [OrganizationController::class, 'assignDiaryAccess']);
            Route::delete('/revoke-diary-access', [OrganizationController::class, 'revokeDiaryAccess']);
        });
        
        // ========================================
        // INVITATIONS (authenticated)
        // ========================================
        Route::prefix('invitations')->group(function () {
            Route::get('/', [InvitationController::class, 'index']);
            Route::post('/employee', [InvitationController::class, 'createEmployeeInvite']);
            Route::post('/client', [InvitationController::class, 'createClientInvite']);
            Route::delete('/{id}', [InvitationController::class, 'revoke']);
        });
        
        // ========================================
        // NOTIFICATIONS
        // ========================================
        Route::get('/notifications', [\App\Http\Controllers\Api\v1\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\v1\NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\v1\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\v1\NotificationController::class, 'markAllRead']);
        
        // ========================================
        // STATS
        // ========================================
        Route::get('/stats/chart', [\App\Http\Controllers\Api\v1\StatsController::class, 'getDiaryChart']);
        Route::get('/stats/tasks', [\App\Http\Controllers\Api\v1\StatsController::class, 'getTaskSummary']);
        
        // ========================================
        // ROUTE SHEET
        // ========================================
        Route::prefix('route-sheet')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'index']);
            Route::get('/my-tasks', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'myTasks']);
            Route::get('/available-employees', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'availableEmployees']);
            Route::get('/{task}', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'store']);
            Route::put('/{task}', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'update']);
            Route::delete('/{task}', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'destroy']);
            Route::post('/{task}/reschedule', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'reschedule']);
            Route::post('/{task}/complete', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'complete']);
            Route::post('/{task}/miss', [\App\Http\Controllers\Api\v1\RouteSheetController::class, 'miss']);
        });
    });
});
