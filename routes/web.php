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
use App\Http\Controllers\VoteController;
use App\Http\Controllers\VoteAuditController;
use App\Http\Controllers\VoteExportController;
use App\Http\Controllers\VotePdfExportController;
use App\Http\Controllers\VoteImportTemplateController;
use App\Http\Controllers\VoteImportController;
use App\Http\Controllers\FinancialYearController;
use App\Http\Controllers\FinancialYearAuditController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubjectAuditController;
use App\Http\Controllers\SubjectExportController;
use App\Http\Controllers\AipActivityOfficeController;

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

    Route::get('/votes', [VoteController::class, 'index'])
        ->name('votes.index');

    Route::get('/votes/{vote}/edit', [VoteController::class, 'edit'])
        ->name('votes.edit');

    Route::post('/votes', [VoteController::class, 'store'])
        ->name('votes.store');

    Route::put('/votes/{vote}', [VoteController::class, 'update'])
        ->name('votes.update');

    Route::get('/vote-audit', [VoteAuditController::class, 'index'])
        ->name('votes.audit');

    Route::get('/votes/export/excel', [VoteExportController::class, 'excel'])
        ->name('votes.export.excel');

    Route::get('/votes/export/pdf', [VotePdfExportController::class, 'download'])
        ->name('votes.export.pdf');

    Route::get('/votes/import/template', [VoteImportTemplateController::class, 'download'])
        ->name('votes.import.template');

    Route::get('/votes/import', [VoteImportController::class, 'index'])
        ->name('votes.import.index');

    Route::post('/votes/import/validate', [VoteImportController::class, 'validateFile'])
        ->name('votes.import.validate');

    Route::post('/votes/import', [VoteImportController::class, 'store'])
        ->name('votes.import.store');

    Route::get('/financial-years', [FinancialYearController::class, 'index'])
        ->name('financial-years.index');

    Route::post('/financial-years', [FinancialYearController::class, 'store'])
        ->name('financial-years.store');

    Route::get('/financial-year-audit', [FinancialYearAuditController::class, 'index'])
        ->name('financial-years.audit');

    Route::get(
        '/financial-years/{financialYear}/edit',
        [FinancialYearController::class, 'edit']
    )->name('financial-years.edit');

    Route::put(
        '/financial-years/{financialYear}',
        [FinancialYearController::class, 'update']
    )->name('financial-years.update');

    Route::get('/subjects', [SubjectController::class, 'index'])
        ->name('subjects.index');

    Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit'])
        ->name('subjects.edit');

    Route::post('/subjects', [SubjectController::class, 'store'])
        ->name('subjects.store');

    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])
        ->name('subjects.update');

    Route::get('/subject-audit', [SubjectAuditController::class, 'index'])
        ->name('subjects.audit');

    Route::get('/subjects/export/excel', [SubjectExportController::class, 'excel'])
        ->name('subjects.export.excel');

    Route::get('/subjects/export/pdf', [
            \App\Http\Controllers\SubjectPdfExportController::class,
            'download',
        ])->name('subjects.export.pdf');

    Route::get('/subjects/import/template', [
            \App\Http\Controllers\SubjectImportTemplateController::class,
            'download',
        ])->name('subjects.import.template');

    Route::get('/subjects/import', [
        \App\Http\Controllers\SubjectImportController::class,
        'index',
        ])->name('subjects.import.index');

    Route::post('/subjects/import/validate', [
        \App\Http\Controllers\SubjectImportController::class,
        'validateFile',
        ])->name('subjects.import.validate');

    Route::post('/subjects/import', [
        \App\Http\Controllers\SubjectImportController::class,
        'store',
    ])->name('subjects.import.store');

    Route::get('/designations', [
        \App\Http\Controllers\DesignationController::class,
        'index',
    ])->name('designations.index');

    Route::post('/designations', [
        \App\Http\Controllers\DesignationController::class,
        'store',
    ])->name('designations.store');

    Route::put('/designations/{designation}', [
        \App\Http\Controllers\DesignationController::class,
        'update',
    ])->name('designations.update');

    Route::get('/designations/audit', [
        \App\Http\Controllers\DesignationAuditController::class,
        'index',
    ])->name('designations.audit');

    Route::get('/designations/export/excel', [
        \App\Http\Controllers\DesignationExportController::class,
        'excel',
    ])->name('designations.export.excel');

    Route::get('/designations/export/pdf', [
        \App\Http\Controllers\DesignationPdfExportController::class,
        'download',
    ])->name('designations.export.pdf');

    Route::get('/designations/import/template', [
        \App\Http\Controllers\DesignationImportTemplateController::class,
        'download',
    ])->name('designations.import.template');

    Route::get('/designations/import', [
        \App\Http\Controllers\DesignationImportController::class,
        'index',
    ])->name('designations.import.index');

    Route::post('/designations/import/validate', [
        \App\Http\Controllers\DesignationImportController::class,
        'validateFile',
    ])->name('designations.import.validate');

    Route::post('/designations/import', [
        \App\Http\Controllers\DesignationImportController::class,
        'store',
    ])->name('designations.import.store');

    Route::get('/officers', [
        \App\Http\Controllers\OfficerController::class,
        'index',
    ])->name('officers.index');

    Route::post('/officers', [
        \App\Http\Controllers\OfficerController::class,
        'store',
    ])->name('officers.store');

    Route::put('/officers/{officer}', [
        \App\Http\Controllers\OfficerController::class,
        'update',
    ])->name('officers.update');

    Route::get('/officers/audit', [
        \App\Http\Controllers\OfficerAuditController::class,
        'index',
    ])->name('officers.audit');

    Route::get('/officers/export/excel', [
        \App\Http\Controllers\OfficerExportController::class,
        'excel',
    ])->name('officers.export.excel');

    Route::get('/officers/export/pdf', [
        \App\Http\Controllers\OfficerPdfExportController::class,
        'download',
    ])->name('officers.export.pdf');

    Route::get('/officers/import/template', [
        \App\Http\Controllers\OfficerImportTemplateController::class,
        'download',
    ])->name('officers.import.template');

    Route::get('/officers/import', [
        \App\Http\Controllers\OfficerImportController::class,
        'index',
    ])->name('officers.import.index');

    Route::post('/officers/import/validate', [
        \App\Http\Controllers\OfficerImportController::class,
        'validateFile',
    ])->name('officers.import.validate');

    Route::post('/officers/import', [
        \App\Http\Controllers\OfficerImportController::class,
        'store',
    ])->name('officers.import.store');

    Route::get('/programme-types', [
        \App\Http\Controllers\ProgrammeTypeController::class,
        'index',
    ])->name('programme-types.index');

    Route::post('/programme-types', [
        \App\Http\Controllers\ProgrammeTypeController::class,
        'store',
    ])->name('programme-types.store');

    Route::put('/programme-types/{programmeType}', [
        \App\Http\Controllers\ProgrammeTypeController::class,
        'update',
    ])->name('programme-types.update');

    Route::get('/programme-types/audit', [
        \App\Http\Controllers\ProgrammeTypeAuditController::class,
        'index',
    ])->name('programme-types.audit');

    Route::get('/programme-types/export/excel', [
        \App\Http\Controllers\ProgrammeTypeExportController::class,
        'excel',
    ])->name('programme-types.export.excel');

    Route::get('/programme-types/export/pdf', [
        \App\Http\Controllers\ProgrammeTypePdfExportController::class,
        'download',
    ])->name('programme-types.export.pdf');

    Route::get('/programme-types/import/template', [
        \App\Http\Controllers\ProgrammeTypeImportTemplateController::class,
        'download',
    ])->name('programme-types.import.template');

    Route::get('/programme-types/import', [
        \App\Http\Controllers\ProgrammeTypeImportController::class,
        'index',
    ])->name('programme-types.import.index');

    Route::post('/programme-types/import/validate', [
        \App\Http\Controllers\ProgrammeTypeImportController::class,
        'validateFile',
    ])->name('programme-types.import.validate');

    Route::post('/programme-types/import', [
        \App\Http\Controllers\ProgrammeTypeImportController::class,
        'store',
    ])->name('programme-types.import.store');

    Route::get('/expense-categories', [
        \App\Http\Controllers\ExpenseCategoryController::class,
        'index',
    ])->name('expense-categories.index');

    Route::post('/expense-categories', [
        \App\Http\Controllers\ExpenseCategoryController::class,
        'store',
    ])->name('expense-categories.store');

    Route::put('/expense-categories/{expenseCategory}', [
        \App\Http\Controllers\ExpenseCategoryController::class,
        'update',
    ])->name('expense-categories.update');

    Route::get('/expense-categories/import/template', [
        \App\Http\Controllers\ExpenseCategoryImportTemplateController::class,
        'download',
    ])->name('expense-categories.import.template');

    Route::get('/expense-categories/import', [
        \App\Http\Controllers\ExpenseCategoryImportController::class,
        'index',
    ])->name('expense-categories.import.index');

    Route::post('/expense-categories/import/validate', [
        \App\Http\Controllers\ExpenseCategoryImportController::class,
        'validateFile',
    ])->name('expense-categories.import.validate');

    Route::post('/expense-categories/import', [
        \App\Http\Controllers\ExpenseCategoryImportController::class,
        'store',
    ])->name('expense-categories.import.store');

    Route::get('/expense-categories/audit', [
        \App\Http\Controllers\ExpenseCategoryAuditController::class,
        'index',
    ])->name('expense-categories.audit');

    Route::get('/estimate-circular-settings', [
        \App\Http\Controllers\EstimateCircularSettingsController::class,
        'index',
    ])->name('estimate-circular-settings.index');

    Route::put('/estimate-circular-settings', [
        \App\Http\Controllers\EstimateCircularSettingsController::class,
        'update',
    ])->name('estimate-circular-settings.update');

    Route::get('/estimates/create', [
        \App\Http\Controllers\EstimateController::class,
        'create',
    ])->name('estimates.create');

    Route::post('/estimates', [
        \App\Http\Controllers\EstimateController::class,
        'store',
    ])->name('estimates.store');

    Route::get('/estimates/{id}', [
        \App\Http\Controllers\EstimateController::class,
        'show',
    ])->name('estimates.show');

    Route::get('/officer-subject-assignments', [
        \App\Http\Controllers\OfficerSubjectAssignmentController::class,
        'index',
    ])->name('officer-subject-assignments.index');

    Route::post('/officer-subject-assignments', [
        \App\Http\Controllers\OfficerSubjectAssignmentController::class,
        'store',
    ])->name('officer-subject-assignments.store');

    Route::patch('/officer-subject-assignments/{id}/end', [
        \App\Http\Controllers\OfficerSubjectAssignmentController::class,
        'end',
    ])->name('officer-subject-assignments.end');

    Route::get('/aip-activities', [
        \App\Http\Controllers\AipActivityController::class,
        'index',
    ])->name('aip-activities.index');

    Route::post('/aip-activities', [
        \App\Http\Controllers\AipActivityController::class,
        'store',
    ])->name('aip-activities.store');

    Route::get('/aip-activities/audit', [
        \App\Http\Controllers\AipActivityAuditController::class,
        'index',
    ])->name('aip-activities.audit');

    Route::get('/aip-activity-offices', [AipActivityOfficeController::class, 'index'])
        ->name('aip-activity-offices.index');

    Route::post('/aip-activity-offices', [AipActivityOfficeController::class, 'store'])
        ->name('aip-activity-offices.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
