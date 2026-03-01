<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\HelpArticleController as AdminHelpArticleController;
use App\Http\Controllers\Admin\HelpManagementController;
use App\Http\Controllers\Admin\HelpTourController as AdminHelpTourController;
use App\Http\Controllers\AssetReportController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuditHistoryReportController;
use App\Http\Controllers\AuditReportController;
use App\Http\Controllers\BulkExportController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\BulkQrCodeController;
use App\Http\Controllers\CapacityReportController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConnectionHistoryController;
use App\Http\Controllers\ConnectionReportController;
use App\Http\Controllers\ConnectionTemplateController;
use App\Http\Controllers\CustomReportBuilderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatacenterController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceTypeController;
use App\Http\Controllers\DiscrepancyController;
use App\Http\Controllers\DistributionListController;
use App\Http\Controllers\EquipmentMoveController;
use App\Http\Controllers\ExpectedConnectionController;
use App\Http\Controllers\FileComparisonController;
use App\Http\Controllers\FindingCategoryController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\FindingEvidenceController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\ImplementationFileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParseConnectionsController;
use App\Http\Controllers\PduController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\ReportScheduleController;
use App\Http\Controllers\RoleAssignmentController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RowController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TemplateDownloadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// Dashboard chart data endpoint (authenticated users - access control in controller)
Route::get('dashboard/charts', [DashboardController::class, 'chartData'])
    ->middleware(['auth'])
    ->name('dashboard.charts');

// User management routes (Administrator only)
Route::middleware(['auth', 'role:Administrator'])->group(function () {
    // User CRUD routes
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Bulk status change
    Route::post('users/bulk-status', [UserController::class, 'bulkStatus'])->name('users.bulk-status');

    // Legacy role assignment routes (kept for backward compatibility)
    Route::get('users/roles', function () {
        return redirect()->route('users.index');
    })->name('users.roles.index');
    Route::put('users/{user}/role', [RoleAssignmentController::class, 'update'])->name('users.roles.update');
});

// Admin help content management routes (Administrator only)
Route::middleware(['auth', 'role:Administrator'])->prefix('admin/help')->group(function () {
    // Help Management Inertia Pages
    Route::get('/', [HelpManagementController::class, 'index'])->name('admin.help.index');

    // Article Inertia Pages
    Route::get('articles/create', [HelpManagementController::class, 'createArticle'])->name('admin.help.articles.create');
    Route::get('articles/{article}/edit', [HelpManagementController::class, 'editArticle'])->name('admin.help.articles.edit');
    Route::get('articles/{article}/preview', [HelpManagementController::class, 'previewArticle'])->name('admin.help.articles.preview');

    // Article CRUD (form submissions)
    Route::post('articles', [HelpManagementController::class, 'storeArticle'])->name('admin.help.articles.store');
    Route::put('articles/{article}', [HelpManagementController::class, 'updateArticle'])->name('admin.help.articles.update');
    Route::delete('articles/{article}', [HelpManagementController::class, 'destroyArticle'])->name('admin.help.articles.destroy');

    // Tour Inertia Pages
    Route::get('tours/create', [HelpManagementController::class, 'createTour'])->name('admin.help.tours.create');
    Route::get('tours/{tour}/edit', [HelpManagementController::class, 'editTour'])->name('admin.help.tours.edit');
    Route::get('tours/{tour}/preview', [HelpManagementController::class, 'previewTour'])->name('admin.help.tours.preview');

    // Tour CRUD (form submissions)
    Route::post('tours', [HelpManagementController::class, 'storeTour'])->name('admin.help.tours.store');
    Route::put('tours/{tour}', [HelpManagementController::class, 'updateTour'])->name('admin.help.tours.update');
    Route::delete('tours/{tour}', [HelpManagementController::class, 'destroyTour'])->name('admin.help.tours.destroy');

    // API endpoints for JSON responses (used by frontend for real-time operations)
    Route::get('api/articles', [AdminHelpArticleController::class, 'index'])->name('admin.help.api.articles.index');
    Route::get('api/articles/{article}', [AdminHelpArticleController::class, 'show'])->name('admin.help.api.articles.show');
    Route::get('api/tours', [AdminHelpTourController::class, 'index'])->name('admin.help.api.tours.index');
    Route::get('api/tours/{tour}', [AdminHelpTourController::class, 'show'])->name('admin.help.api.tours.show');
});

// Custom Report Builder routes (IT Manager, Administrator, Auditor only)
Route::middleware(['auth', 'role:IT Manager|Administrator|Auditor'])->group(function () {
    Route::get('custom-reports', [CustomReportBuilderController::class, 'index'])->name('custom-reports.index');
    Route::get('custom-reports/configure', [CustomReportBuilderController::class, 'configure'])->name('custom-reports.configure');
    Route::post('custom-reports/preview', [CustomReportBuilderController::class, 'preview'])->name('custom-reports.preview');
    Route::post('custom-reports/export/pdf', [CustomReportBuilderController::class, 'exportPdf'])->name('custom-reports.export.pdf');
    Route::post('custom-reports/export/csv', [CustomReportBuilderController::class, 'exportCsv'])->name('custom-reports.export.csv');
    Route::post('custom-reports/export/json', [CustomReportBuilderController::class, 'exportJson'])->name('custom-reports.export.json');
});

// Datacenter management routes (all authenticated users with policy-based authorization)
Route::middleware(['auth'])->group(function () {
    // Help Center routes (all authenticated users)
    Route::get('help', [HelpCenterController::class, 'index'])->name('help.index');
    Route::get('help/{helpArticle}', [HelpCenterController::class, 'show'])->name('help.show');

    // Global search results page (all authenticated users - RBAC filtering in controller)
    Route::get('search', [SearchController::class, 'index'])->name('search.index');

    Route::resource('datacenters', DatacenterController::class);

    // Datacenter connection comparison page route (authorization in controller)
    Route::get('datacenters/{datacenter}/connection-comparison', [DatacenterController::class, 'connectionComparison'])
        ->name('datacenters.connection-comparison');

    // Room routes nested under datacenter
    Route::resource('datacenters.rooms', RoomController::class);

    // Row routes nested under datacenter.room
    Route::resource('datacenters.rooms.rows', RowController::class);

    // Rack routes nested under datacenter.room.row
    Route::resource('datacenters.rooms.rows.racks', RackController::class);

    // Rack elevation view route
    Route::get('datacenters/{datacenter}/rooms/{room}/rows/{row}/racks/{rack}/elevation', [RackController::class, 'elevation'])
        ->name('datacenters.rooms.rows.racks.elevation');

    // PDU routes nested under datacenter.room
    Route::resource('datacenters.rooms.pdus', PduController::class);

    // Implementation files routes nested under datacenter (policy-based authorization)
    Route::get('datacenters/{datacenter}/implementation-files', [ImplementationFileController::class, 'index'])
        ->name('datacenters.implementation-files.index');
    Route::post('datacenters/{datacenter}/implementation-files', [ImplementationFileController::class, 'store'])
        ->name('datacenters.implementation-files.store');
    Route::get('datacenters/{datacenter}/implementation-files/{implementation_file}', [ImplementationFileController::class, 'show'])
        ->name('datacenters.implementation-files.show');
    Route::get('datacenters/{datacenter}/implementation-files/{implementation_file}/download', [ImplementationFileController::class, 'download'])
        ->name('datacenters.implementation-files.download');
    Route::get('datacenters/{datacenter}/implementation-files/{implementation_file}/preview', [ImplementationFileController::class, 'preview'])
        ->name('datacenters.implementation-files.preview');
    Route::get('datacenters/{datacenter}/implementation-files/{implementation_file}/versions', [ImplementationFileController::class, 'versions'])
        ->name('datacenters.implementation-files.versions');
    Route::post('datacenters/{datacenter}/implementation-files/{implementation_file}/restore', [ImplementationFileController::class, 'restore'])
        ->name('datacenters.implementation-files.restore');
    Route::post('datacenters/{datacenter}/implementation-files/{implementation_file}/approve', [ImplementationFileController::class, 'approve'])
        ->name('datacenters.implementation-files.approve');
    Route::delete('datacenters/{datacenter}/implementation-files/{implementation_file}', [ImplementationFileController::class, 'destroy'])
        ->name('datacenters.implementation-files.destroy');

    // Parse connections from implementation files (authorization in controller)
    Route::post('implementation-files/{implementationFile}/parse-connections', [ParseConnectionsController::class, 'parse'])
        ->name('implementation-files.parse-connections');

    // Implementation file comparison page route (authorization in controller)
    Route::get('implementation-files/{file}/comparison', [FileComparisonController::class, 'comparison'])
        ->name('implementation-files.comparison');

    // Expected connections review routes (authorization in controller)
    // Inertia page route for connection review
    Route::get('expected-connections/review', [ExpectedConnectionController::class, 'review'])
        ->name('expected-connections.review');
    // API routes for expected connections
    Route::get('expected-connections', [ExpectedConnectionController::class, 'index'])
        ->name('expected-connections.index');
    Route::get('expected-connections/{expectedConnection}', [ExpectedConnectionController::class, 'show'])
        ->name('expected-connections.show');
    Route::put('expected-connections/{expectedConnection}', [ExpectedConnectionController::class, 'update'])
        ->name('expected-connections.update');
    Route::post('expected-connections/bulk-confirm', [ExpectedConnectionController::class, 'bulkConfirm'])
        ->name('expected-connections.bulk-confirm');
    Route::post('expected-connections/bulk-skip', [ExpectedConnectionController::class, 'bulkSkip'])
        ->name('expected-connections.bulk-skip');
    Route::post('expected-connections/{expectedConnection}/create-device-port', [ExpectedConnectionController::class, 'createDevicePort'])
        ->name('expected-connections.create-device-port');

    // Connection template download routes (authorization in controller)
    Route::get('templates/connections/excel', [ConnectionTemplateController::class, 'excel'])
        ->name('templates.connections.excel');
    Route::get('templates/connections/csv', [ConnectionTemplateController::class, 'csv'])
        ->name('templates.connections.csv');

    // Notification routes (all authenticated users)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Discrepancy dashboard route (all authenticated users)
    Route::get('discrepancies', [DiscrepancyController::class, 'index'])->name('discrepancies.index');

    // Capacity planning reports routes (all authenticated users - access control in controller)
    Route::get('capacity-reports', [CapacityReportController::class, 'index'])->name('capacity-reports.index');
    Route::get('capacity-reports/export/pdf', [CapacityReportController::class, 'exportPdf'])->name('capacity-reports.export.pdf');
    Route::get('capacity-reports/export/csv', [CapacityReportController::class, 'exportCsv'])->name('capacity-reports.export.csv');

    // Asset reports routes (all authenticated users - access control in controller)
    Route::get('reports/assets', [AssetReportController::class, 'index'])->name('asset-reports.index');
    Route::get('reports/assets/export/pdf', [AssetReportController::class, 'exportPdf'])->name('asset-reports.export.pdf');
    Route::get('reports/assets/export/csv', [AssetReportController::class, 'exportCsv'])->name('asset-reports.export.csv');

    // Audit history reports routes (all authenticated users - access control in controller)
    Route::get('reports/audit-history', [AuditHistoryReportController::class, 'index'])->name('audit-history-reports.index');
    Route::get('reports/audit-history/export/pdf', [AuditHistoryReportController::class, 'exportPdf'])->name('audit-history-reports.export.pdf');
    Route::get('reports/audit-history/export/csv', [AuditHistoryReportController::class, 'exportCsv'])->name('audit-history-reports.export.csv');

    // Connection reports routes (all authenticated users - access control in controller)
    Route::get('connection-reports', [ConnectionReportController::class, 'index'])->name('connection-reports.index');
    Route::get('connection-reports/export/pdf', [ConnectionReportController::class, 'exportPdf'])->name('connection-reports.export.pdf');
    Route::get('connection-reports/export/csv', [ConnectionReportController::class, 'exportCsv'])->name('connection-reports.export.csv');

    // Device types routes (policy-based authorization)
    Route::get('device-types', [DeviceTypeController::class, 'index'])->name('device-types.index');
    Route::get('device-types/create', [DeviceTypeController::class, 'create'])->name('device-types.create');
    Route::post('device-types', [DeviceTypeController::class, 'store'])->name('device-types.store');
    Route::get('device-types/{device_type}/edit', [DeviceTypeController::class, 'edit'])->name('device-types.edit');
    Route::put('device-types/{device_type}', [DeviceTypeController::class, 'update'])->name('device-types.update');
    Route::delete('device-types/{device_type}', [DeviceTypeController::class, 'destroy'])->name('device-types.destroy');

    // Rack API routes - Global route for all racks (policy-based authorization)
    Route::get('racks', [RackController::class, 'allRacks'])->name('racks.all');

    // Device API routes - Global route for inventory management (policy-based authorization)
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('devices/create', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    // Device placement API endpoints for rack elevation integration
    Route::patch('devices/{device}/place', [DeviceController::class, 'place'])->name('devices.place');
    Route::patch('devices/{device}/unplace', [DeviceController::class, 'unplace'])->name('devices.unplace');

    // Port routes nested under devices (policy-based authorization via Form Request)
    Route::get('devices/{device}/ports', [PortController::class, 'index'])->name('devices.ports.index');
    Route::get('devices/{device}/ports/diagram', [PortController::class, 'diagram'])->name('devices.ports.diagram');
    Route::post('devices/{device}/ports', [PortController::class, 'store'])->name('devices.ports.store');
    Route::get('devices/{device}/ports/{port}', [PortController::class, 'show'])->name('devices.ports.show');
    Route::put('devices/{device}/ports/{port}', [PortController::class, 'update'])->name('devices.ports.update');
    Route::delete('devices/{device}/ports/{port}', [PortController::class, 'destroy'])->name('devices.ports.destroy');
    Route::post('devices/{device}/ports/bulk', [PortController::class, 'bulk'])->name('devices.ports.bulk');

    // Port pairing routes for patch panel support
    Route::post('devices/{device}/ports/{port}/pair', [PortController::class, 'pair'])->name('devices.ports.pair');
    Route::delete('devices/{device}/ports/{port}/pair', [PortController::class, 'unpair'])->name('devices.ports.unpair');

    // Equipment move routes (policy-based authorization)
    Route::get('equipment-moves', [EquipmentMoveController::class, 'index'])->name('equipment-moves.index');
    Route::post('equipment-moves', [EquipmentMoveController::class, 'store'])->name('equipment-moves.store');
    Route::get('equipment-moves/{equipmentMove}', [EquipmentMoveController::class, 'show'])->name('equipment-moves.show');
    Route::get('equipment-moves/{equipmentMove}/work-order', [EquipmentMoveController::class, 'downloadWorkOrder'])->name('equipment-moves.work-order');
    Route::post('equipment-moves/{equipmentMove}/approve', [EquipmentMoveController::class, 'approve'])->name('equipment-moves.approve');
    Route::post('equipment-moves/{equipmentMove}/reject', [EquipmentMoveController::class, 'reject'])->name('equipment-moves.reject');
    Route::post('equipment-moves/{equipmentMove}/cancel', [EquipmentMoveController::class, 'cancel'])->name('equipment-moves.cancel');

    // Connection history routes (must be before {connection} routes to avoid route conflicts)
    Route::get('connections/history', [ConnectionHistoryController::class, 'index'])->name('connections.history.index');

    // Connection history export routes
    Route::post('connections/history/export', [ConnectionHistoryController::class, 'export'])->name('connections.history.export');
    Route::get('connections/history/export/{bulkExport}/status', [ConnectionHistoryController::class, 'exportStatus'])->name('connections.history.export.status');
    Route::get('connections/history/export/{bulkExport}/download', [ConnectionHistoryController::class, 'exportDownload'])->name('connections.history.export.download');

    // Connection diagram routes (must be before {connection} routes to avoid route conflicts)
    Route::get('connections/diagram/page', [ConnectionController::class, 'diagramPage'])->name('connections.diagram.page');
    Route::get('connections/diagram', [ConnectionController::class, 'diagram'])->name('connections.diagram');

    // Connection routes (policy-based authorization)
    Route::get('connections', [ConnectionController::class, 'index'])->name('connections.index');
    Route::post('connections', [ConnectionController::class, 'store'])->name('connections.store');
    Route::get('connections/{connection}', [ConnectionController::class, 'show'])->name('connections.show');
    Route::put('connections/{connection}', [ConnectionController::class, 'update'])->name('connections.update');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');

    // Connection timeline route (for specific connection history)
    Route::get('connections/{connection}/timeline', [ConnectionHistoryController::class, 'timeline'])->name('connections.timeline');

    // Nested device routes under datacenter.room.row.rack for rack-scoped views
    Route::get('datacenters/{datacenter}/rooms/{room}/rows/{row}/racks/{rack}/devices', [DeviceController::class, 'index'])
        ->name('datacenters.rooms.rows.racks.devices.index');

    // Bulk import routes (authorization in controller - Administrator, IT Manager)
    Route::get('imports', [BulkImportController::class, 'index'])->name('imports.index');
    Route::get('imports/create', [BulkImportController::class, 'create'])->name('imports.create');
    Route::post('imports', [BulkImportController::class, 'store'])->name('imports.store');
    Route::get('imports/{bulkImport}', [BulkImportController::class, 'show'])->name('imports.show');
    Route::get('imports/{bulkImport}/errors', [BulkImportController::class, 'downloadErrors'])->name('imports.errors');

    // Import template download routes (authorization in controller)
    Route::get('imports/templates/{entityType}', [TemplateDownloadController::class, 'download'])
        ->name('imports.templates.download');
    Route::get('imports/templates', [TemplateDownloadController::class, 'downloadCombined'])
        ->name('imports.templates.combined');

    // Bulk export routes (authorization in controller - Administrator, IT Manager)
    Route::get('exports', [BulkExportController::class, 'index'])->name('exports.index');
    Route::get('exports/create', [BulkExportController::class, 'create'])->name('exports.create');
    Route::post('exports', [BulkExportController::class, 'store'])->name('exports.store');
    Route::get('exports/{bulkExport}', [BulkExportController::class, 'show'])->name('exports.show');
    Route::get('exports/{bulkExport}/download', [BulkExportController::class, 'download'])->name('exports.download');

    // Bulk QR code routes (all authenticated users with view access)
    Route::get('qr-codes/bulk', [BulkQrCodeController::class, 'create'])->name('qr-codes.bulk.create');
    Route::post('qr-codes/bulk', [BulkQrCodeController::class, 'generate'])->name('qr-codes.bulk.generate');

    // Audit dashboard route (must be before resource routes to avoid route conflicts)
    // Authorization in controller - Administrator, IT Manager, Auditor
    Route::get('audits/dashboard', [AuditController::class, 'dashboard'])->name('audits.dashboard');

    // Audit routes (authorization in form request - Administrator, IT Manager, Auditor)
    Route::resource('audits', AuditController::class)->only(['index', 'create', 'store', 'show']);

    // Audit execution routes
    Route::post('audits/{audit}/start', [AuditController::class, 'startExecution'])->name('audits.start');
    Route::get('audits/{audit}/execute', [AuditController::class, 'execute'])->name('audits.execute');
    Route::get('audits/{audit}/inventory-execute', [AuditController::class, 'inventoryExecute'])->name('audits.inventory-execute');

    // Audit report generation route (authorization in controller)
    Route::post('audits/{audit}/reports', [AuditReportController::class, 'generate'])->name('audits.reports.generate');

    // Reports routes (authorization in controller)
    Route::get('reports', [AuditReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [AuditReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/download', [AuditReportController::class, 'download'])->name('reports.download');

    // Finding bulk operations (must be before {finding} routes to avoid route conflicts)
    Route::post('findings/bulk-assign', [FindingController::class, 'bulkAssign'])->name('findings.bulk-assign');
    Route::post('findings/bulk-status', [FindingController::class, 'bulkStatus'])->name('findings.bulk-status');

    // Finding routes (authorization in controller and form request)
    Route::get('findings', [FindingController::class, 'index'])->name('findings.index');
    Route::get('findings/{finding}', [FindingController::class, 'show'])->name('findings.show');
    Route::put('findings/{finding}', [FindingController::class, 'update'])->name('findings.update');

    // Finding quick transition route
    Route::post('findings/{finding}/transition', [FindingController::class, 'transition'])->name('findings.transition');

    // Finding evidence routes (authorization in form request and controller)
    Route::post('findings/{finding}/evidence', [FindingEvidenceController::class, 'store'])->name('findings.evidence.store');
    Route::delete('findings/{finding}/evidence/{evidence}', [FindingEvidenceController::class, 'destroy'])->name('findings.evidence.destroy');

    // Finding category routes (authorization in form request - any authenticated user)
    Route::get('finding-categories', [FindingCategoryController::class, 'index'])->name('finding-categories.index');
    Route::post('finding-categories', [FindingCategoryController::class, 'store'])->name('finding-categories.store');

    // Distribution list routes (policy-based authorization)
    Route::resource('distribution-lists', DistributionListController::class);

    // Report schedule routes (policy-based authorization)
    Route::resource('report-schedules', ReportScheduleController::class)->except(['edit', 'update']);
    Route::patch('report-schedules/{reportSchedule}/toggle', [ReportScheduleController::class, 'toggle'])
        ->name('report-schedules.toggle');
    Route::get('report-schedules/{reportSchedule}/history', [ReportScheduleController::class, 'history'])
        ->name('report-schedules.history');
});

// Activity logs routes (all authenticated users, role-based filtering in controller)
Route::middleware(['auth'])->group(function () {
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// Stub routes for disabled features (needed for Wayfinder)
Route::get('email/verify', function () {
    // Email verification is disabled - show message
    return Inertia::render('auth/VerifyEmail');
})->middleware(['auth'])->name('verification.notice');

Route::post('email/verification-notification', function () {
    abort(404, 'Email verification is not enabled');
})->middleware(['auth'])->name('verification.send');

Route::get('register', function () {
    abort(404, 'Registration is not enabled');
})->name('register');

Route::post('register', function () {
    abort(404, 'Registration is not enabled');
})->name('register.store');

require __DIR__.'/settings.php';
