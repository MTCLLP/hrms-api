<?php

use Illuminate\Support\Facades\Route;
use Modules\Localization\Http\Controllers\LocalizationController;
use Modules\Localization\Http\Controllers\CountryController;
use Modules\Localization\Http\Controllers\StateController;
use Modules\Localization\Http\Controllers\CityController;
use Modules\Localization\Http\Controllers\GetCitiesByCountryController;
use Modules\Localization\Http\Controllers\GetStatesByCountryController;
use Modules\Localization\Http\Controllers\GetCitiesByStateController;
use Modules\Localization\Http\Controllers\LanguageController;

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
    Route::apiResource('localization', LocalizationController::class)->names('localization');
});

Route::group(
	[
		'middleware' => ['auth:api'],
		'prefix' => 'manage/localization',
		'namespace' => 'Api\backend\localization'
	],
	function() {
        Route::get('/countries', [CountryController::class, 'index'])->name('api.countries.index');
		Route::get('/countries/paginated', [CountryController::class, 'paginated'])->name('api.countries.paginated');
		Route::get('/countries/trash', [CountryController::class, 'trash'])->name('api.countries.trash');
		Route::get('/countries/restore/{id}', [CountryController::class, 'restore'])->name('api.countries.restore');
        Route::post('/countries', [CountryController::class, 'store'])->name('api.countries.store');
        Route::get('/countries/{country}', [CountryController::class, 'show'])->name('api.countries.show');
        Route::patch('/countries/{country}', [CountryController::class, 'update'])->name('api.countries.update');
		Route::delete('/countries/{id}', [CountryController::class, 'destroy'])->name('api.countries.destroy');
		Route::delete('/countries', [CountryController::class, 'destroyMany'])->name('api.countries.destroyMany');


        Route::get('/getstatesbycountry/{country}', [GetStatesByCountryController::class, 'show'])->name('api.statesbycountry.show');

        Route::get('/states', [StateController::class, 'index'])->name('api.states.index');
		Route::get('/states/paginated', [StateController::class, 'paginated'])->name('api.states.paginated');
		Route::get('/states/trash', [StateController::class, 'trash'])->name('api.states.trash');
		Route::get('/states/restore/{id}', [StateController::class, 'restore'])->name('api.states.restore');
        Route::post('/states', [StateController::class, 'store'])->name('api.states.store');
        Route::get('/states/{state}', [StateController::class, 'show'])->name('api.states.show');
        Route::patch('/states/{state}', [StateController::class, 'update'])->name('api.states.update');
		Route::delete('/states/{id}', [StateController::class, 'destroy'])->name('api.states.destroy');

        Route::get('/getcitiesbystate/{state}', [GetCitiesByStateController::class, 'show'])->name('api.citiesbystate.show');

        Route::get('/getcitiesbycountry/{country}', [GetCitiesByCountryController::class, 'show'])->name('api.citiesbycountry.show');

        Route::get('/cities', [CityController::class, 'index'])->name('api.cities.index');
		Route::get('/cities/paginated', [CityController::class, 'paginated'])->name('api.cities.paginated');
        Route::get('/cities/dropdown', [CityController::class, 'dropdown'])->name('api.cities.dropdown');
		Route::get('/cities/trash', [CityController::class, 'trash'])->name('api.cities.trash');
		Route::get('/cities/restore/{id}', [CityController::class, 'restore'])->name('api.cities.restore');
        Route::post('/cities', [CityController::class, 'store'])->name('api.cities.store');
        Route::get('/cities/{city}', [CityController::class, 'show'])->name('api.cities.show');
        Route::patch('/cities/{city}', [CityController::class, 'update'])->name('api.cities.update');
		Route::delete('/cities/{id}', [CityController::class, 'destroy'])->name('api.cities.destroy');

		Route::get('/languages', [LanguageController::class, 'index'])->name('api.languages.index');
		Route::get('/languages/paginated', [LanguageController::class, 'paginated'])->name('api.languages.paginated');
		Route::get('/languages/trash', [LanguageController::class, 'trash'])->name('api.languages.trash');
		Route::get('/languages/restore/{id}', [LanguageController::class, 'restore'])->name('api.languages.restore');
        Route::post('/languages', [LanguageController::class, 'store'])->name('api.languages.store');
        Route::get('/languages/{language}', [LanguageController::class, 'show'])->name('api.languages.show');
        Route::patch('/languages/{language}', [LanguageController::class, 'update'])->name('api.languages.update');
		Route::delete('/languages/{id}', [LanguageController::class, 'destroy'])->name('api.languages.destroy');

	}
);

