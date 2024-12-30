<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Http\Controllers\OrganizationController;
use Modules\Organization\Http\Controllers\DepartmentsController;
use Modules\Organization\Http\Controllers\BranchController;

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
    Route::apiResource('organization', OrganizationController::class)->names('organization');
});

Route::group(
	[
        'middleware' => ['auth:api'],
        'prefix' => 'manage/hr',
    ],
    function () {
        Route::get('/departments', [DepartmentsController::class, 'index'])->name('api.departments.index');
		Route::get('/departments/paginated', [DepartmentsController::class, 'paginated'])->name('api.departments.paginated');
		Route::get('/departments/trash', [DepartmentsController::class, 'trash'])->name('api.departments.trash');
		Route::get('/departments/restore/{id}', [DepartmentsController::class, 'restore'])->name('api.departments.restore');
        Route::post('/departments', [DepartmentsController::class, 'store'])->name('api.departments.store');
        Route::get('/departments/{department}', [DepartmentsController::class, 'show'])->name('api.departments.show');
        Route::patch('/departments/{department}', [DepartmentsController::class, 'update'])->name('api.departments.update');
		Route::delete('/departments/{id}', [DepartmentsController::class, 'destroy'])->name('api.departments.destroy');
        Route::post('/departments-delete-multiple', [DepartmentsController::class, 'destroyMultiple'])->name('api.departments.destroy-multiple');


        Route::get('/branches', [BranchController::class, 'index'])->name('api.branches.index');
		Route::get('/branches/paginated', [BranchController::class, 'paginated'])->name('api.branches.paginated');
		Route::get('/branches/trash', [BranchController::class, 'trash'])->name('api.branches.trash');
		Route::get('/branches/restore/{id}', [BranchController::class, 'restore'])->name('api.branches.restore');
        Route::post('/branches', [BranchController::class, 'store'])->name('api.branches.store');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])->name('api.branches.show');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->name('api.branches.update');
		Route::delete('/branches/{id}', [BranchController::class, 'destroy'])->name('api.branches.destroy');
        Route::post('/branches-delete-multiple', [BranchController::class, 'destroyMultiple'])->name('api.branches.destroy-multiple');
    });
