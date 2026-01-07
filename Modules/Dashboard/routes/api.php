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

Route::group(
    [
        'middleware' => ['auth:sanctum'],
        'prefix' => 'manage/dashboard',
        'namespace' => 'Api\Dashboard',
    ],
    function () {
        Route::post('confirm-employee', [DashboardController::class, 'confirmEmployee'])->name('api.dashboard.confirmEmployee');
        Route::post('extend-confirmation', [DashboardController::class, 'extendConfirmation'])->name('api.dashboard.extendConfirmation');
        Route::post('revoke-confirmation', [DashboardController::class, 'revokeConfirmation'])->name('api.dashboard.revokeConfirmation');
        Route::get('get-employees', [DashboardController::class, 'getEmployees'])->name('api.dashboard.getEmployees');
        Route::get('get-pending-leaves', [DashboardController::class, 'getPendingLeaves'])->name('api.dashboard.getPendingLeaves');
        Route::get('get-subordinate-leaves', [DashboardController::class, 'getSubordinateLeaves'])->name('api.dashboard.getSubordinateLeaves');
        Route::get('get-last-leave', [DashboardController::class, 'getLastLeave'])->name('api.dashboard.getLastLeave');
        Route::get('get-calendar-data', [DashboardController::class, 'displayCalendar'])->name('api.dashboard.displayCalendar');
        Route::get('get-upcoming-leaves', [DashboardController::class, 'upcomingLeaves'])->name('api.dashboard.upcomingLeaves');
        Route::get('get-upcoming-birthdays', [DashboardController::class, 'upcomingBirthdays'])->name('api.dashboard.upcomingBirthdays');
        Route::get('get-upcoming-holiday', [DashboardController::class, 'getUpcomingHoliday'])->name('api.dashboard.getUpcomingHoliday');
        Route::get('get-monthly-leaves', [DashboardController::class, 'getEmployeeMonthlyLeaves'])->name('api.dashboard.getEmployeeMonthlyLeaves');
        Route::get('get-upcoming-confirmations', [DashboardController::class, 'upcomingConfirmations'])->name('api.dashboard.upcomingConfirmations');

    }
);
