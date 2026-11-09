<?php

use App\Http\Controllers\FundAuditController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\FundExportController;
use App\Http\Controllers\FundImportController;
use App\Http\Controllers\FundImportTemplateController;
use App\Http\Controllers\FundPdfExportController;
use App\Http\Controllers\OfficeAuditController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeExportController;
use App\Http\Controllers\OfficeImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth', 'can:manage-master-data'])->group(function () {
    Route::get('/office-audit', [OfficeAuditController::class, 'index'])
        ->name('offices.audit');

    Route::get('/offices', [OfficeController::class, 'index'])
        ->name('offices.index');

    Route::get('/offices/{office}/edit', [OfficeController::class, 'edit'])
        ->name('offices.edit');

    Route::post('/offices', [OfficeController::class, 'store'])
        ->name('offices.store');

    Route::put('/offices/{office}', [OfficeController::class, 'update'])
        ->name('offices.update');

    Route::get('/offices/export/excel', [OfficeExportController::class, 'excel'])
        ->name('offices.export.excel');

    Route::get('/offices/export/pdf', [OfficeExportController::class, 'pdf'])
        ->name('offices.export.pdf');

    Route::get('/offices/import/template', [OfficeExportController::class, 'importTemplate'])
        ->name('offices.import.template');

    Route::get('/offices/import', [OfficeImportController::class, 'index'])
        ->name('offices.import.index');

    Route::post('/offices/import/validate', [OfficeImportController::class, 'validateFile'])
        ->name('offices.import.validate');

    Route::post('/offices/import', [OfficeImportController::class, 'store'])
        ->name('offices.import.store');

    Route::get('/fund-audit', [FundAuditController::class, 'index'])
        ->name('funds.audit');

    Route::get('/funds/export/excel', [FundExportController::class, 'excel'])
        ->name('funds.export.excel');

    Route::get('/funds/export/pdf', [FundPdfExportController::class, 'download'])
        ->name('funds.export.pdf');

    Route::get('/funds/import/template', [FundImportTemplateController::class, 'download'])
        ->name('funds.import.template');

    Route::get('/funds/import', [FundImportController::class, 'index'])
        ->name('funds.import.index');

    Route::post('/funds/import/validate', [FundImportController::class, 'validateFile'])
        ->name('funds.import.validate');

    Route::post('/funds/import', [FundImportController::class, 'store'])
        ->name('funds.import.store');

    Route::get('/funds', [FundController::class, 'index'])
        ->name('funds.index');

    Route::get('/funds/{fund}/edit', [FundController::class, 'edit'])
        ->name('funds.edit');

    Route::post('/funds', [FundController::class, 'store'])
        ->name('funds.store');

    Route::put('/funds/{fund}', [FundController::class, 'update'])
        ->name('funds.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
