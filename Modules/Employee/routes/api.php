<?php

use Illuminate\Support\Facades\Route;
use Modules\Employee\Http\Controllers\EmployeeController;
use Modules\Employee\Http\Controllers\EmployeeContactsController;
use Modules\Employee\Http\Controllers\EmployeeAddressController;
use Modules\Employee\Http\Controllers\EmployeeEmailsController;
use Modules\Employee\Http\Controllers\EmployeeDependantsController;
use Modules\Employee\Http\Controllers\JobTitlesController;
use Modules\Employee\Http\Controllers\EmployeeExperienceController;
use Modules\Employee\Http\Controllers\ReportingMethodController;
use Modules\Employee\Http\Controllers\JobReportingController;


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

Route::middleware('auth:api')->get('/hr', function (Request $request) {
    return $request->user();
});

Route::group(
	[
        'middleware' => ['auth:api'],
        'prefix' => 'manage/hr',
    ],
    function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('api.employees.index');
        Route::get('/employees/paginated', [EmployeeController::class, 'paginated'])->name('api.employees.paginated');
        Route::get('/employees/trash', [EmployeeController::class, 'trash'])->name('api.employees.trash');
        Route::get('/employees/restore/{id}', [EmployeeController::class, 'restore'])->name('api.employees.restore');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('api.employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('api.employees.show');
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->name('api.employees.update');
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->name('api.employees.destroy');
        Route::post('/employee-delete-multiple', [EmployeeController::class, 'destroyMultiple'])->name('api.employees.destroy-multiple');

        Route::get('/employee-contacts', [EmployeeContactsController::class, 'index'])->name('api.employee-contacts.index');
		Route::get('/employee-contacts/paginated', [EmployeeContactsController::class, 'paginated'])->name('api.employee-contacts.paginated');
		Route::get('/employee-contacts/trash', [EmployeeContactsController::class, 'trash'])->name('api.employee-contacts.trash');
		Route::get('/employee-contacts/restore/{id}', [EmployeeContactsController::class, 'restore'])->name('api.employee-contacts.restore');
        Route::post('/employee-contacts', [EmployeeContactsController::class, 'store'])->name('api.employee-contacts.store');
        Route::get('/employee-contacts/{employeeContact}', [EmployeeContactsController::class, 'show'])->name('api.employee-contacts.show');
        Route::patch('/employee-contacts/{employeeContact}', [EmployeeContactsController::class, 'update'])->name('api.employee-contacts.update');
		Route::delete('/employee-contacts/{id}', [EmployeeContactsController::class, 'destroy'])->name('api.employee-contacts.destroy');
        Route::post('/employee-contacts-delete-multiple', [EmployeeContactsController::class, 'destroyMultiple'])->name('api.employees-contacts.destroy-multiple');

        Route::get('/employee-address', [EmployeeAddressController::class,'index'])->name('api.employee-address.index');
		Route::get('/employee-address/paginated', [EmployeeAddressController::class,'paginated'])->name('api.employee-address.paginated');
		Route::get('/employee-address/trash', [EmployeeAddressController::class,'trash'])->name('api.employee-address.trash');
		Route::get('/employee-address/restore/{id}', [EmployeeAddressController::class,'restore'])->name('api.employee-address.restore');
        Route::post('/employee-address', [EmployeeAddressController::class,'store'])->name('api.employee-address.store');
        Route::get('/employee-address/{employeeAddress}', [EmployeeAddressController::class,'show'])->name('api.employee-address.show');
        Route::patch('/employee-address/{employeeAddress}', [EmployeeAddressController::class,'update'])->name('api.employee-address.update');
		Route::delete('/employee-address/{id}', [EmployeeAddressController::class,'destroy'])->name('api.employee-address.destroy');
        Route::post('/employee-address-delete-multiple', [EmployeeAddressController::class,'destroyMultiple'])->name('api.employee-address.destroy-multiple');

        Route::get('/employee-emails', [EmployeeEmailsController::class,'index'])->name('api.employee-emails.index');
		Route::get('/employee-emails/paginated', [EmployeeEmailsController::class,'paginated'])->name('api.employee-emails.paginated');
		Route::get('/employee-emails/trash', [EmployeeEmailsController::class,'trash'])->name('api.employee-emails.trash');
		Route::get('/employee-emails/restore/{id}', [EmployeeEmailsController::class,'restore'])->name('api.employee-emails.restore');
        Route::post('/employee-emails', [EmployeeEmailsController::class,'store'])->name('api.employee-emails.store');
        Route::get('/employee-emails/{employeeEmail}', [EmployeeEmailsController::class,'show'])->name('api.employee-emails.show');
        Route::patch('/employee-emails/{employeeEmail}', [EmployeeEmailsController::class,'update'])->name('api.employee-emails.update');
		Route::delete('/employee-emails/{id}', [EmployeeEmailsController::class,'destroy'])->name('api.employee-emails.destroy');
        Route::post('/employee-emails-delete-multiple', [EmployeeEmailsController::class,'destroyMultiple'])->name('api.employees-emails.destroy-multiple');

        Route::get('/employee-dependants', [EmployeeDependantsController::class, 'index'])->name('api.employee-dependants.index');
		Route::get('/employee-dependants/paginated', [EmployeeDependantsController::class, 'paginated'])->name('api.employee-dependants.paginated');
		Route::get('/employee-dependants/trash', [EmployeeDependantsController::class, 'trash'])->name('api.employee-dependants.trash');
		Route::get('/employee-dependants/restore/{id}', [EmployeeDependantsController::class, 'restore'])->name('api.employee-dependants.restore');
        Route::post('/employee-dependants', [EmployeeDependantsController::class, 'store'])->name('api.employee-dependants.store');
        Route::get('/employee-dependants/{employeeDependant}', [EmployeeDependantsController::class, 'show'])->name('api.employee-dependants.show');
        Route::get('/employee-dependants-by-employee/{employee_id}', [EmployeeDependantsController::class, 'getEmployeeDependantsByEmployeeId'])->name('api.employee-dependants.by-employee');
        Route::patch('/employee-dependants/{employeeDependant}', [EmployeeDependantsController::class, 'update'])->name('api.employee-dependants.update');
		Route::delete('/employee-dependants/{id}', [EmployeeDependantsController::class, 'destroy'])->name('api.employee-dependants.destroy');
        Route::post('/employee-dependants-delete-multiple', [EmployeeDependantsController::class, 'destroyMultiple'])->name('api.employee-dependants.destroy-multiple');


        Route::get('/job-titles', [JobTitlesController::class,'index'])->name('api.job-titles.index');
		Route::get('/job-titles/paginated', [JobTitlesController::class,'paginated'])->name('api.job-titles.paginated');
		Route::get('/job-titles/trash', [JobTitlesController::class,'trash'])->name('api.job-titles.trash');
		Route::get('/job-titles/restore/{id}', [JobTitlesController::class,'restore'])->name('api.job-titles.restore');
        Route::post('/job-titles', [JobTitlesController::class,'store'])->name('api.job-titles.store');
        Route::get('/job-titles/{jobTitle}', [JobTitlesController::class,'show'])->name('api.job-titles.show');
        Route::patch('/job-titles/{jobTitle}', [JobTitlesController::class,'update'])->name('api.job-titles.update');
		Route::delete('/job-titles/{id}', [JobTitlesController::class,'destroy'])->name('api.job-titles.destroy');
        Route::post('/job-titles-delete-multiple', [JobTitlesController::class,'destroyMultiple'])->name('api.job-titles.destroy-multiple');


        Route::get('/reporting-methods', [ReportingMethodController::class,'index'])->name('api.reporting-methods.index');
		Route::get('/reporting-methods/paginated', [ReportingMethodController::class,'paginated'])->name('api.reporting-methods.paginated');
		Route::get('/reporting-methods/trash', [ReportingMethodController::class,'trash'])->name('api.reporting-methods.trash');
		Route::get('/reporting-methods/restore/{id}', [ReportingMethodController::class,'restore'])->name('api.reporting-methods.restore');
        Route::post('/reporting-methods', [ReportingMethodController::class,'store'])->name('api.reporting-methods.store');
        Route::get('/reporting-methods/{reportingMethod}', [ReportingMethodController::class,'show'])->name('api.reporting-methods.show');
        Route::patch('/reporting-methods/{reportingMethod}', [ReportingMethodController::class,'update'])->name('api.reporting-methods.update');
		Route::delete('/reporting-methods/{id}', [ReportingMethodController::class,'destroy'])->name('api.reporting-methods.destroy');
        Route::post('/reporting-methods-delete-multiple', [ReportingMethodController::class,'destroyMultiple'])->name('api.reporting-methods.destroy-multiple');


        Route::get('/employee-experience', [EmployeeExperienceController::class,'index'])->name('api.employee-experience.index');
		Route::get('/employee-experience/paginated', [EmployeeExperienceController::class,'paginated'])->name('api.employee-experience.paginated');
		Route::get('/employee-experience/trash', [EmployeeExperienceController::class,'trash'])->name('api.employee-experience.trash');
		Route::get('/employee-experience/restore/{id}', [EmployeeExperienceController::class,'restore'])->name('api.employee-experience.restore');
        Route::post('/employee-experience', [EmployeeExperienceController::class,'store'])->name('api.employee-experience.store');
        Route::get('/employee-experience/{employeeExperience}', [EmployeeExperienceController::class,'show'])->name('api.employee-experience.show');
        Route::patch('/employee-experience/{employeeExperience}', [EmployeeExperienceController::class,'update'])->name('api.employee-experience.update');
		Route::delete('/employee-experience/{id}', [EmployeeExperienceController::class,'destroy'])->name('api.employee-experience.destroy');
        Route::post('/employee-experience-delete-multiple', [EmployeeExperienceController::class,'destroyMultiple'])->name('api.employees-experience.destroy-multiple');


        Route::get('/job-reporting', [JobReportingController::class,'index'])->name('api.job-reporting.index');
		Route::get('/job-reporting/paginated', [JobReportingController::class,'paginated'])->name('api.job-reporting.paginated');
		Route::get('/job-reporting/trash', [JobReportingController::class,'trash'])->name('api.job-reporting.trash');
		Route::get('/job-reporting/restore/{id}', [JobReportingController::class,'restore'])->name('api.job-reporting.restore');
        Route::post('/job-reporting', [JobReportingController::class,'store'])->name('api.job-reporting.store');
        Route::get('/job-reporting/{jobReporting}', [JobReportingController::class,'show'])->name('api.job-reporting.show');
        Route::patch('/job-reporting/{jobReporting}', [JobReportingController::class,'update'])->name('api.job-reporting.update');
		Route::delete('/job-reporting/{id}', [JobReportingController::class,'destroy'])->name('api.job-reporting.destroy');
        Route::post('/job-reporting-delete-multiple', [JobReportingController::class,'destroyMultiple'])->name('api.job-reporting.destroy-multiple');
        Route::post('/job-reporting/add-superior',[JobReportingController::class, 'addSuperior'])->name('api.job-reporting.addSuperior');
        Route::post('/job-reporting/add-subordinate',[JobReportingController::class, 'addSubordinate'])->name('api.job-reporting.addSubordinate');




	}
);
