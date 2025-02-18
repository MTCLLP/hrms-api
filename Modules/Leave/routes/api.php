<?php

use Illuminate\Support\Facades\Route;
use Modules\Leave\Http\Controllers\LeaveController;
use Modules\Leave\Http\Controllers\LeaveBalanceController;
use Modules\Leave\Http\Controllers\LeaveEntitlementController;
use Modules\Leave\Http\Controllers\LeaveRequestController;
use Modules\Leave\Http\Controllers\LeaveSettingController;
use Modules\Leave\Http\Controllers\LeaveTypeController;
use Modules\Leave\Http\Controllers\LeaveApprovalController;
use Modules\Leave\Http\Controllers\HolidayController;

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

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('leave', LeaveController::class)->names('leave');
});

Route::group(
	[
        'middleware' => ['auth:api'],
        'prefix' => 'manage/hr',
    ],
    function () {
        Route::get('/leave-balance', [LeaveBalanceController::class, 'index'])->name('api.leave-balance.index');
        Route::get('/leave-balance/paginated', [LeaveBalanceController::class, 'paginated'])->name('api.leave-balance.paginated');
        Route::get('/leave-balance/trash', [LeaveBalanceController::class, 'trash'])->name('api.leave-balance.trash');
        Route::get('/leave-balance/restore/{id}', [LeaveBalanceController::class, 'restore'])->name('api.leave-balance.restore');
        Route::post('/leave-balance', [LeaveBalanceController::class, 'store'])->name('api.leave-balance.store');
        Route::get('/leave-balance/{leaveBalance}', [LeaveBalanceController::class, 'show'])->name('api.leave-balance.show');
        Route::patch('/leave-balance/{leaveBalance}', [LeaveBalanceController::class, 'update'])->name('api.leave-balance.update');
        Route::delete('/leave-balance/{id}', [LeaveBalanceController::class, 'destroy'])->name('api.leave-balance.destroy');
        Route::post('/leave-balance-delete-multiple', [LeaveBalanceController::class, 'destroyMultiple'])->name('api.leave-balance.destroy-multiple');

        Route::get('/leave-entitlement', [LeaveEntitlementController::class, 'index'])->name('api.leave-entitlement.index');
		Route::get('/leave-entitlement/paginated', [LeaveEntitlementController::class, 'paginated'])->name('api.leave-entitlement.paginated');
		Route::get('/leave-entitlement/trash', [LeaveEntitlementController::class, 'trash'])->name('api.leave-entitlement.trash');
		Route::get('/leave-entitlement/restore/{id}', [LeaveEntitlementController::class, 'restore'])->name('api.leave-entitlement.restore');
        Route::post('/leave-entitlement', [LeaveEntitlementController::class, 'store'])->name('api.leave-entitlement.store');
        Route::get('/leave-entitlement/{leaveEntitlement}', [LeaveEntitlementController::class, 'show'])->name('api.leave-entitlement.show');
        Route::patch('/leave-entitlement/{leaveEntitlement}', [LeaveEntitlementController::class, 'update'])->name('api.leave-entitlement.update');
		Route::delete('/leave-entitlement/{id}', [LeaveEntitlementController::class, 'destroy'])->name('api.leave-entitlement.destroy');
        Route::post('/leave-entitlement-delete-multiple', [LeaveEntitlementController::class, 'destroyMultiple'])->name('api.leave-entitlement.destroy-multiple');

        Route::get('/leave-request', [LeaveRequestController::class,'index'])->name('api.leave-request.index');
		Route::get('/leave-request/paginated', [LeaveRequestController::class,'paginated'])->name('api.leave-request.paginated');
		Route::get('/leave-request/trash', [LeaveRequestController::class,'trash'])->name('api.leave-request.trash');
		Route::get('/leave-request/restore/{id}', [LeaveRequestController::class,'restore'])->name('api.leave-request.restore');
        Route::post('/leave-request', [LeaveRequestController::class,'store'])->name('api.leave-request.store');
        Route::get('/leave-request/{leaveRequest}', [LeaveRequestController::class,'show'])->name('api.leave-request.show');
        Route::patch('/leave-request/{leaveRequest}', [LeaveRequestController::class,'update'])->name('api.leave-request.update');
		Route::delete('/leave-request/{id}', [LeaveRequestController::class,'destroy'])->name('api.leave-request.destroy');
        Route::post('/leave-request-delete-multiple', [LeaveRequestController::class,'destroyMultiple'])->name('api.leave-request.destroy-multiple');
        Route::post('/leave-request/approve-leave', [LeaveRequestController::class, 'approveLeave'])->name('api.leave-request.approve-leave');
        Route::post('/leave-request/reject-leave', [LeaveRequestController::class, 'rejectLeave'])->name('api.leave-request.reject-leave');
        Route::post('/leave-request/partial-approve-leave', [LeaveRequestController::class, 'partialApproval'])->name('api.leave-request.partial-approve-leave');

        Route::get('/leave-setting', [LeaveSettingController::class,'index'])->name('api.leave-setting.index');
		Route::get('/leave-setting/paginated', [LeaveSettingController::class,'paginated'])->name('api.leave-setting.paginated');
		Route::get('/leave-setting/trash', [LeaveSettingController::class,'trash'])->name('api.leave-setting.trash');
		Route::get('/leave-setting/restore/{id}', [LeaveSettingController::class,'restore'])->name('api.leave-setting.restore');
        Route::post('/leave-setting', [LeaveSettingController::class,'store'])->name('api.leave-setting.store');
        Route::get('/leave-setting/{leaveSetting}', [LeaveSettingController::class,'show'])->name('api.leave-setting.show');
        Route::patch('/leave-setting/{leaveSetting}', [LeaveSettingController::class,'update'])->name('api.leave-setting.update');
		Route::delete('/leave-setting/{id}', [LeaveSettingController::class,'destroy'])->name('api.leave-setting.destroy');
        Route::post('/leave-setting-delete-multiple', [LeaveSettingController::class,'destroyMultiple'])->name('api.leave-setting.destroy-multiple');

        Route::get('/leave-type', [LeaveTypeController::class, 'index'])->name('api.leave-type.index');
		Route::get('/leave-type/paginated', [LeaveTypeController::class, 'paginated'])->name('api.leave-type.paginated');
		Route::get('/leave-type/trash', [LeaveTypeController::class, 'trash'])->name('api.leave-type.trash');
		Route::get('/leave-type/restore/{id}', [LeaveTypeController::class, 'restore'])->name('api.leave-type.restore');
        Route::post('/leave-type', [LeaveTypeController::class, 'store'])->name('api.leave-type.store');
        Route::get('/leave-type/{leaveType}', [LeaveTypeController::class, 'show'])->name('api.leave-type.show');
        Route::patch('/leave-type/{leaveType}', [LeaveTypeController::class, 'update'])->name('api.leave-type.update');
		Route::delete('/leave-type/{id}', [LeaveTypeController::class, 'destroy'])->name('api.leave-type.destroy');
        Route::post('/leave-type-delete-multiple', [LeaveTypeController::class, 'destroyMultiple'])->name('api.leave-type.destroy-multiple');

        Route::get('/leave-approval', [LeaveApprovalController::class, 'index'])->name('api.leave-approval.index');
		Route::get('/leave-approval/paginated', [LeaveApprovalController::class, 'paginated'])->name('api.leave-approval.paginated');
		Route::get('/leave-approval/trash', [LeaveApprovalController::class, 'trash'])->name('api.leave-approval.trash');
		Route::get('/leave-approval/restore/{id}', [LeaveApprovalController::class, 'restore'])->name('api.leave-approval.restore');
        Route::post('/leave-approval', [LeaveApprovalController::class, 'store'])->name('api.leave-approval.store');
        Route::get('/leave-approval/{leaveApproval}', [LeaveApprovalController::class, 'show'])->name('api.leave-approval.show');
        Route::patch('/leave-approval/{leaveApproval}', [LeaveApprovalController::class, 'update'])->name('api.leave-approval.update');
		Route::delete('/leave-approval/{id}', [LeaveApprovalController::class, 'destroy'])->name('api.leave-approval.destroy');
        Route::post('/leave-approval-delete-multiple', [LeaveApprovalController::class, 'destroyMultiple'])->name('api.leave-approval.destroy-multiple');


        Route::get('/holiday', [HolidayController::class, 'index'])->name('api.holiday.index');
		Route::get('/holiday/paginated', [HolidayController::class, 'paginated'])->name('api.holiday.paginated');
		Route::get('/holiday/trash', [HolidayController::class, 'trash'])->name('api.holiday.trash');
		Route::get('/holiday/restore/{id}', [HolidayController::class, 'restore'])->name('api.holiday.restore');
        Route::post('/holiday', [HolidayController::class, 'store'])->name('api.holiday.store');
        Route::get('/holiday/{holiday}', [HolidayController::class, 'show'])->name('api.holiday.show');
        Route::patch('/holiday/{holiday}', [HolidayController::class, 'update'])->name('api.holiday.update');
		Route::delete('/holiday/{id}', [HolidayController::class, 'destroy'])->name('api.holiday.destroy');
        Route::post('/holiday-delete-multiple', [HolidayController::class, 'destroyMultiple'])->name('api.holiday.destroy-multiple');
	}
);
