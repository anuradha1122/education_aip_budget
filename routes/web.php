<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeAuditController;
use App\Http\Controllers\OfficeExportController;

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

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
