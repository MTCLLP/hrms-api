<?php

use Illuminate\Support\Facades\Route;
use Modules\Asset\Http\Controllers\HardwareAssetController;
use Modules\Asset\Http\Controllers\SoftwareLicenseController;
use Modules\Asset\Http\Controllers\HardwareAssignmentController;
use Modules\Asset\Http\Controllers\SoftwareAssignmentController;

Route::middleware(['auth:sanctum'])->prefix('v1/asset')->group(function () {
    Route::apiResource('hardware-assets', HardwareAssetController::class);
    Route::apiResource('software-licenses', SoftwareLicenseController::class);
    Route::apiResource('hardware-assignments', HardwareAssignmentController::class);
    Route::apiResource('software-assignments', SoftwareAssignmentController::class);
});
