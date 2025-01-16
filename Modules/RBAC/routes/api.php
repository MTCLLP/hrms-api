<?php

use Illuminate\Support\Facades\Route;
use Modules\RBAC\Http\Controllers\RBACController;
use Modules\RBAC\Http\Controllers\Api\Auth\RegisterController;
use Modules\RBAC\Http\Controllers\Api\Auth\LoginController;
use Modules\RBAC\Http\Controllers\Api\Auth\ChangePasswordController;
use Modules\RBAC\Http\Controllers\Api\RBAC\UserController;
use Modules\RBAC\Http\Controllers\Api\RBAC\UserProfileController;
use Modules\RBAC\Http\Controllers\Api\RBAC\RoleController;
use Modules\RBAC\Http\Controllers\Api\RBAC\PermissionController;
use Modules\RBAC\Http\Controllers\Api\Auth\PasswordResetController;
use Modules\RBAC\Http\Controllers\Api\RBAC\DashboardController;
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
    Route::apiResource('rbac', RBACController::class)->names('rbac');
});

Route::group(
	[
		'middleware' => ['api'],
		'prefix' => 'manage/rbac',
		'namespace' => 'Api\Auth'
    ],
    function(){
        Route::post('/register', [RegisterController::class, 'createUser'])->name('register.api');
        Route::post('/login', [LoginController::class, 'login'])->name('login.api');
        Route::post('/login-mobile', [LoginController::class, 'loginMobile'])->name('login-mobile.api');
        Route::post('/forgotPassword', [PasswordResetController::class, 'sendResetLinkEmail'])->name('forgotPass.api');
        Route::post('/resetPassword', [PasswordResetController::class, 'reset'])->name('password.reset');


    }
);

Route::group(
	[
		'middleware' => ['auth:sanctum'],
		'prefix' => 'manage/rbac',
		'namespace' => 'Api\RBAC'
    ],
    function(){

        Route::post('/change-password', [ChangePasswordController::class, 'changePassword'])->name('change-password.api');

        Route::get('permissions',[PermissionController::class, 'index'])->name('api.permissions.index');
        Route::get('permissions/paginated',[PermissionController::class, 'paginated'])->name('api.permissions.paginated');
        Route::post('permissions',[PermissionController::class, 'store'])->name('api.permissions.store');
        Route::get('permissions/{permission}',[PermissionController::class, 'show'])->name('api.permissions.show');
        Route::put('permissions/{permission}',[PermissionController::class, 'update'])->name('api.permissions.update');
        Route::delete('permissions/{id}',[PermissionController::class, 'destroy'])->name('api.permissions.destroy');

        Route::get('/users',[UserController::class, 'index'])->name('api.users.index');
        Route::get('users/paginated',[UserController::class, 'paginated'])->name('api.users.paginated');
        Route::post('users',[UserController::class, 'store'])->name('api.users.store');
        Route::get('users/{user}',[UserController::class, 'show'])->name('api.users.show');
        Route::put('users/{user}',[UserController::class, 'update'])->name('api.users.update');
        Route::delete('users/{id}',[UserController::class, 'destroy'])->name('api.users.destroy');

        Route::get('user-profile/{user_profile}',[UserProfileController::class, 'show'])->name('api.user_profile.show');

        Route::patch('user-profile/{user_profile}',[UserProfileController::class, 'update'])->name('api.user_profile.update');
        Route::delete('user-profile/{id}',[UserProfileController::class, 'destroy'])->name('api.user-profile.destroy');

        Route::get('roles',[RoleController::class, 'index'])->name('api.roles.index');
        Route::get('roles/paginated',[RoleController::class, 'paginated'])->name('api.roles.paginated');
        Route::post('roles',[RoleController::class, 'store'])->name('api.roles.store');
        Route::get('roles/{role}',[RoleController::class, 'show'])->name('api.roles.show');
        Route::put('roles/{role}',[RoleController::class, 'update'])->name('api.roles.update');
        Route::delete('roles/{id}',[RoleController::class, 'destroy'])->name('api.roles.destroy');

        Route::get('get-employees',[DashboardController::class, 'getEmployees'])->name('api.dashboard.getEmployees');
        Route::get('get-pending-leaves',[DashboardController::class, 'getPendingLeaves'])->name('api.dashboard.getPendingLeaves');
        Route::get('get-subordinate-leaves',[DashboardController::class, 'getSubordinateLeaves'])->name('api.dashboard.getSubordinateLeaves');
        Route::get('get-last-leave',[DashboardController::class, 'getLastLeave'])->name('api.dashboard.getLastLeave');
        Route::get('get-calendar-data',[DashboardController::class,'displayCalendar'])->name('api.dashboard.displayCalendar');
    }
);
