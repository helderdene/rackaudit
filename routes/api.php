<?php

use App\Http\Controllers\Api\AuditConnectionVerificationController;
use App\Http\Controllers\Api\AuditDataController;
use App\Http\Controllers\Api\AuditDeviceVerificationController;
use App\Http\Controllers\Api\ConnectionComparisonController;
use App\Http\Controllers\Api\DiscrepancyAcknowledgmentController;
use App\Http\Controllers\Api\DiscrepancyController;
use App\Http\Controllers\Api\EquipmentMoveApiController;
use App\Http\Controllers\Api\Help\HelpArticleController;
use App\Http\Controllers\Api\Help\HelpTourController;
use App\Http\Controllers\Api\Help\UserHelpInteractionController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Connection comparison routes (requires authentication)
// Using 'web' middleware to enable session-based auth for browser requests
Route::middleware(['web', 'auth'])->group(function () {
    // Search API endpoints
    Route::prefix('search')->group(function () {
        Route::get('/quick', [SearchController::class, 'quickSearch'])
            ->name('api.search.quick');
        Route::get('/', [SearchController::class, 'search'])
            ->name('api.search');
    });

    // Implementation file comparison
    Route::get('implementation-files/{file}/comparison', [ConnectionComparisonController::class, 'compareForFile'])
        ->name('api.implementation-files.comparison');

    // Implementation file comparison export
    Route::get('implementation-files/{file}/comparison/export', [ConnectionComparisonController::class, 'exportForFile'])
        ->name('api.implementation-files.comparison.export');

    // Datacenter comparison
    Route::get('datacenters/{datacenter}/connection-comparison', [ConnectionComparisonController::class, 'compareForDatacenter'])
        ->name('api.datacenters.connection-comparison');

    // Datacenter comparison export
    Route::get('datacenters/{datacenter}/connection-comparison/export', [ConnectionComparisonController::class, 'exportForDatacenter'])
        ->name('api.datacenters.connection-comparison.export');

    // Discrepancy acknowledgments (for comparison view)
    Route::post('discrepancy-acknowledgments', [DiscrepancyAcknowledgmentController::class, 'store'])
        ->name('api.discrepancy-acknowledgments.store');
    Route::delete('discrepancy-acknowledgments/{acknowledgment}', [DiscrepancyAcknowledgmentController::class, 'destroy'])
        ->name('api.discrepancy-acknowledgments.destroy');

    // Discrepancy management endpoints
    Route::prefix('discrepancies')->group(function () {
        Route::get('/', [DiscrepancyController::class, 'index'])
            ->name('api.discrepancies.index');
        Route::get('/summary', [DiscrepancyController::class, 'summary'])
            ->name('api.discrepancies.summary');
        Route::post('/detect', [DiscrepancyController::class, 'detect'])
            ->name('api.discrepancies.detect');
        Route::post('/bulk-status', [DiscrepancyController::class, 'bulkStatus'])
            ->name('api.discrepancies.bulk-status');
        Route::get('/{discrepancy}', [DiscrepancyController::class, 'show'])
            ->name('api.discrepancies.show');
        Route::patch('/{discrepancy}/acknowledge', [DiscrepancyController::class, 'acknowledge'])
            ->name('api.discrepancies.acknowledge');
        Route::patch('/{discrepancy}/resolve', [DiscrepancyController::class, 'resolve'])
            ->name('api.discrepancies.resolve');
    });

    // Audit cascading dropdown data endpoints
    Route::get('audits/datacenters/{datacenter}/rooms', [AuditDataController::class, 'rooms'])
        ->name('api.audits.datacenters.rooms');
    Route::get('audits/rooms/{room}/racks', [AuditDataController::class, 'racks'])
        ->name('api.audits.rooms.racks');
    Route::get('audits/racks/devices', [AuditDataController::class, 'devices'])
        ->name('api.audits.racks.devices');
    Route::get('audits/assignable-users', [AuditDataController::class, 'assignableUsers'])
        ->name('api.audits.assignable-users');

    // Audit implementation file status endpoint
    Route::get('audits/datacenters/{datacenter}/implementation-file-status', [AuditDataController::class, 'implementationFileStatus'])
        ->name('api.audits.datacenters.implementation-file-status');

    // Audit connection verification endpoints
    Route::prefix('audits/{audit}/verifications')->group(function () {
        Route::get('/', [AuditConnectionVerificationController::class, 'index'])
            ->name('api.audits.verifications.index');
        Route::get('/stats', [AuditConnectionVerificationController::class, 'stats'])
            ->name('api.audits.verifications.stats');
        Route::get('/{verification}', [AuditConnectionVerificationController::class, 'show'])
            ->name('api.audits.verifications.show');
        Route::post('/{verification}/verify', [AuditConnectionVerificationController::class, 'verify'])
            ->name('api.audits.verifications.verify');
        Route::post('/{verification}/discrepant', [AuditConnectionVerificationController::class, 'discrepant'])
            ->name('api.audits.verifications.discrepant');
        Route::post('/{verification}/lock', [AuditConnectionVerificationController::class, 'lock'])
            ->name('api.audits.verifications.lock');
        Route::delete('/{verification}/lock', [AuditConnectionVerificationController::class, 'unlock'])
            ->name('api.audits.verifications.unlock');
        Route::post('/bulk-verify', [AuditConnectionVerificationController::class, 'bulkVerify'])
            ->name('api.audits.verifications.bulk-verify');
    });

    // Audit device verification endpoints (for inventory audits)
    Route::prefix('audits/{audit}/device-verifications')->group(function () {
        Route::get('/', [AuditDeviceVerificationController::class, 'index'])
            ->name('api.audits.device-verifications.index');
        Route::get('/stats', [AuditDeviceVerificationController::class, 'stats'])
            ->name('api.audits.device-verifications.stats');
        Route::post('/{verification}/verify', [AuditDeviceVerificationController::class, 'verify'])
            ->name('api.audits.device-verifications.verify');
        Route::post('/{verification}/not-found', [AuditDeviceVerificationController::class, 'notFound'])
            ->name('api.audits.device-verifications.not-found');
        Route::post('/{verification}/discrepant', [AuditDeviceVerificationController::class, 'discrepant'])
            ->name('api.audits.device-verifications.discrepant');
        Route::post('/bulk-verify', [AuditDeviceVerificationController::class, 'bulkVerify'])
            ->name('api.audits.device-verifications.bulk-verify');
        Route::post('/{verification}/lock', [AuditDeviceVerificationController::class, 'lock'])
            ->name('api.audits.device-verifications.lock');
        Route::delete('/{verification}/lock', [AuditDeviceVerificationController::class, 'unlock'])
            ->name('api.audits.device-verifications.unlock');
    });

    // Equipment move wizard support endpoints
    Route::prefix('devices')->group(function () {
        Route::get('/search', [EquipmentMoveApiController::class, 'searchDevices'])
            ->name('api.devices.search');
    });

    Route::prefix('locations')->group(function () {
        Route::get('/hierarchy', [EquipmentMoveApiController::class, 'locationHierarchy'])
            ->name('api.locations.hierarchy');
    });

    Route::prefix('racks')->group(function () {
        Route::get('/{rack}/devices', [EquipmentMoveApiController::class, 'rackDevices'])
            ->name('api.racks.devices');
    });

    // Help content API endpoints (authenticated, rate limited)
    Route::prefix('help')->middleware('throttle:60,1')->group(function () {
        // Public help article endpoints
        Route::get('/articles', [HelpArticleController::class, 'index'])
            ->name('api.help.articles.index');
        Route::get('/articles/search', [HelpArticleController::class, 'search'])
            ->name('api.help.articles.search');
        Route::get('/articles/{helpArticle}', [HelpArticleController::class, 'show'])
            ->name('api.help.articles.show');

        // Public help tour endpoints
        Route::get('/tours/{helpTour}', [HelpTourController::class, 'show'])
            ->name('api.help.tours.show');
        Route::get('/tours/context/{contextKey}', [HelpTourController::class, 'forContext'])
            ->name('api.help.tours.context');

        // User interaction endpoints
        Route::prefix('user')->group(function () {
            Route::get('/dismissed', [UserHelpInteractionController::class, 'dismissed'])
                ->name('api.help.user.dismissed');
            Route::get('/completed-tours', [UserHelpInteractionController::class, 'completedTours'])
                ->name('api.help.user.completed-tours');
            Route::post('/interactions', [UserHelpInteractionController::class, 'store'])
                ->name('api.help.user.interactions.store');
        });
    });
});
