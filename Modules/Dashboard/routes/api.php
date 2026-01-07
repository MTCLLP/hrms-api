<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\Api\DashboardController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:sanctum'])->prefix('manage/dashboard')->group(function () {
    Route::post('confirm-employee', [DashboardController::class, 'confirmEmployee'])->name('dashboard.confirmEmployee');
    Route::post('extend-confirmation', [DashboardController::class, 'extendConfirmation'])->name('dashboard.extendConfirmation');
    Route::post('revoke-confirmation', [DashboardController::class, 'revokeConfirmation'])->name('dashboard.revokeConfirmation');
    Route::get('get-employees', [DashboardController::class, 'getEmployees'])->name('dashboard.getEmployees');
    Route::get('get-pending-leaves', [DashboardController::class, 'getPendingLeaves'])->name('dashboard.getPendingLeaves');
    Route::get('get-subordinate-leaves', [DashboardController::class, 'getSubordinateLeaves'])->name('dashboard.getSubordinateLeaves');
    Route::get('get-last-leave', [DashboardController::class, 'getLastLeave'])->name('dashboard.getLastLeave');
    Route::get('get-calendar-data', [DashboardController::class, 'displayCalendar'])->name('dashboard.displayCalendar');
    Route::get('get-upcoming-leaves', [DashboardController::class, 'upcomingLeaves'])->name('dashboard.upcomingLeaves');
    Route::get('get-upcoming-birthdays', [DashboardController::class, 'upcomingBirthdays'])->name('dashboard.upcomingBirthdays');
    Route::get('get-upcoming-holiday', [DashboardController::class, 'getUpcomingHoliday'])->name('dashboard.getUpcomingHoliday');
    Route::get('get-monthly-leaves', [DashboardController::class, 'getEmployeeMonthlyLeaves'])->name('dashboard.getEmployeeMonthlyLeaves');
    Route::get('get-upcoming-confirmations', [DashboardController::class, 'upcomingConfirmations'])->name('dashboard.upcomingConfirmations');
});
