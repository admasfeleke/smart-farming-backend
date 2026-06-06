<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CropController;
use App\Http\Controllers\Api\V1\DiseasePreventionController;
use App\Http\Controllers\Api\V1\DiseaseReportController;
use App\Http\Controllers\Api\V1\DiseaseReportMediaController;
use App\Http\Controllers\Api\V1\FarmController;
use App\Http\Controllers\Api\V1\PlantingController;
use App\Http\Controllers\Api\V1\PlotController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\ScanMetadataTrendController;
use App\Http\Controllers\Api\V1\SoilHealthController;
use App\Http\Controllers\Api\V1\WeatherDataController;
use App\Http\Controllers\Api\V1\YieldPredictionController;
use App\Http\Middleware\EnsureAccessTokenNotExpired;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('disease-reports/{report}/media/original', [DiseaseReportMediaController::class, 'original'])
        ->middleware('signed')
        ->name('api.v1.disease-reports.media.original');
    Route::get('disease-reports/{report}/media/evidence/{evidence}', [DiseaseReportMediaController::class, 'evidence'])
        ->middleware('signed')
        ->name('api.v1.disease-reports.media.evidence');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:api-auth');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:api-auth');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:api-auth');

    Route::middleware(['auth:sanctum', EnsureAccessTokenNotExpired::class])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('throttle:api-write');
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('health', [\App\Http\Controllers\Api\V1\HealthController::class, 'show']);

        Route::get('farms', [FarmController::class, 'index']);
        Route::get('farms/{farm}', [FarmController::class, 'show']);
        Route::post('farms', [FarmController::class, 'store'])->middleware('throttle:api-write');
        Route::put('farms/{farm}', [FarmController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('farms/{farm}', [FarmController::class, 'destroy'])->middleware('throttle:api-write');

        Route::get('farms/{farm}/plots', [PlotController::class, 'indexByFarm']);
        Route::get('plots/{plot}', [PlotController::class, 'show']);
        Route::post('farms/{farm}/plots', [PlotController::class, 'store'])->middleware('throttle:api-write');
        Route::put('plots/{plot}', [PlotController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('plots/{plot}', [PlotController::class, 'destroy'])->middleware('throttle:api-write');

        Route::get('plots/{plot}/plantings', [PlantingController::class, 'indexByPlot']);
        Route::get('plantings/{planting}', [PlantingController::class, 'show']);
        Route::post('plots/{plot}/plantings', [PlantingController::class, 'store'])->middleware('throttle:api-write');
        Route::put('plantings/{planting}', [PlantingController::class, 'update'])->middleware('throttle:api-write');
        Route::delete('plantings/{planting}', [PlantingController::class, 'destroy'])->middleware('throttle:api-write');

        Route::post('disease-reports', [DiseaseReportController::class, 'store'])->middleware('throttle:api-write');
        Route::post('disease-reports/scan', [DiseaseReportController::class, 'scan'])->middleware('throttle:api-write');
        Route::get('disease-reports', [DiseaseReportController::class, 'index']);
        Route::get('disease-reports/{report}', [DiseaseReportController::class, 'show']);
        Route::get('disease-reports/{report}/media/original/file', [DiseaseReportMediaController::class, 'originalAuthenticated'])
            ->name('api.v1.disease-reports.media.original.authenticated');
        Route::get('disease-reports/{report}/media/evidence/{evidence}/file', [DiseaseReportMediaController::class, 'evidenceAuthenticated'])
            ->name('api.v1.disease-reports.media.evidence.authenticated');
        Route::put('disease-reports/{report}/verify', [DiseaseReportController::class, 'verify'])->middleware('throttle:api-write');
        Route::get('crop-health', [\App\Http\Controllers\Api\V1\CropHealthController::class, 'index']);
        Route::get('scan-metadata/trends', [ScanMetadataTrendController::class, 'index']);

        Route::get('weather-data', [WeatherDataController::class, 'index']);
        Route::get('weather-data/summary', [WeatherDataController::class, 'summary']);
        Route::get('weather-data/{weatherData}', [WeatherDataController::class, 'show']);
        Route::post('weather-data', [WeatherDataController::class, 'store']);
        Route::put('weather-data/{weatherData}', [WeatherDataController::class, 'update']);
        Route::delete('weather-data/{weatherData}', [WeatherDataController::class, 'destroy']);

        Route::get('soil-health', [SoilHealthController::class, 'index']);
        Route::get('soil-health/summary', [SoilHealthController::class, 'summary']);
        Route::get('soil-health/{soilHealth}/recommendations', [SoilHealthController::class, 'recommendations']);
        Route::get('soil-health/{soilHealth}', [SoilHealthController::class, 'show']);
        Route::post('soil-health', [SoilHealthController::class, 'store']);
        Route::put('soil-health/{soilHealth}', [SoilHealthController::class, 'update']);
        Route::delete('soil-health/{soilHealth}', [SoilHealthController::class, 'destroy']);

        Route::get('alerts', [AlertController::class, 'index']);
        Route::put('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->middleware('throttle:api-write');
        Route::put('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->middleware('throttle:api-write');

        Route::get('crops', [CropController::class, 'index']);
        Route::get('regions', [RegionController::class, 'index']);

        Route::post('yield-prediction', [\App\Http\Controllers\Api\V1\YieldPredictionController::class, 'predict']);
        Route::get('yield-prediction/{planting}', [\App\Http\Controllers\Api\V1\YieldPredictionController::class, 'show']);

        Route::post('disease-prevention/analyze', [\App\Http\Controllers\Api\V1\DiseasePreventionController::class, 'analyze']);
        Route::get('disease-prevention/recommendations', [\App\Http\Controllers\Api\V1\DiseasePreventionController::class, 'getRecommendations']);
    });
});


