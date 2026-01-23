<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiaryController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminTokenAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invite/{token}', function ($token) {
    return redirect("healapp://invite/{$token}");
});

// Admin routes with token auth
Route::prefix('admin')->middleware(['web', AdminTokenAuth::class])->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/delete-all', [UserController::class, 'destroyAllUsers'])->name('users.destroy-all-users');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::delete('/users/{user}/all', [UserController::class, 'destroyAll'])->name('users.destroy-all');

    // Organizations
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');

    // Patients
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');

    // Diaries
    Route::get('/diaries', [DiaryController::class, 'index'])->name('diaries.index');
    Route::get('/diaries/{diary}', [DiaryController::class, 'show'])->name('diaries.show');
    Route::delete('/diaries/{diary}', [DiaryController::class, 'destroy'])->name('diaries.destroy');
});
