<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OfficeImportSaveTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->fromArray([
            ['Office code', 'Office name', 'Office type', 'Status'],
            ...$rows,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'office-save-');

        (new Xlsx($spreadsheet))->save($path);

        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'offices.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_a_regular_user_cannot_import_offices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/offices/import')
            ->assertForbidden();

        $this->assertDatabaseCount('offices', 0);
    }

    public function test_an_administrator_can_import_valid_offices_with_audit_events(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['ZONE-01', 'First Test Office', 'zonal', 'Active'],
            ['ZONE-02', 'Second Test Office', 'zonal', 'Inactive'],
        ]);

        try {
            $this->actingAs($admin)
                ->post('/offices/import/validate', [
                    'file' => $file,
                ])
                ->assertOk();

            $this->actingAs($admin)
                ->post('/offices/import', [
                    'file' => $file,
                ])
                ->assertRedirect('/offices/import')
                ->assertSessionHas(
                    'import_success',
                    '2 offices imported successfully.'
                );

            $this->assertDatabaseHas('offices', [
                'code' => 'ZONE-01',
                'name' => 'First Test Office',
                'is_active' => true,
            ]);

            $this->assertDatabaseHas('offices', [
                'code' => 'ZONE-02',
                'name' => 'Second Test Office',
                'is_active' => false,
            ]);

            $this->assertSame(
                2,
                DB::table('office_audit_events')
                    ->where('actor_id', $admin->id)
                    ->where('action', 'created')
                    ->count()
            );
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_a_different_file_cannot_be_imported_after_preview(): void
    {
        $admin = $this->administrator();

        $reviewedFile = $this->workbook([
            ['REVIEW-01', 'Reviewed Office', 'zonal', 'Active'],
        ]);

        $differentFile = $this->workbook([
            ['OTHER-01', 'Different Office', 'zonal', 'Active'],
        ]);

        try {
            $this->actingAs($admin)
                ->post('/offices/import/validate', [
                    'file' => $reviewedFile,
                ])
                ->assertOk();

            $this->actingAs($admin)
                ->from('/offices/import')
                ->post('/offices/import', [
                    'file' => $differentFile,
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('file');

            $this->assertDatabaseCount('offices', 0);
            $this->assertDatabaseCount('office_audit_events', 0);
        } finally {
            @unlink($reviewedFile->getPathname());
            @unlink($differentFile->getPathname());
        }
    }

    public function test_an_unreviewed_file_with_an_invalid_row_cannot_be_imported(): void
    {
        $file = $this->workbook([
            ['ZONE-01', 'Valid Office', 'zonal', 'Active'],
            ['ZONE-02', 'Invalid Office', 'wrong', 'Active'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/offices/import', [
                    'file' => $file,
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('file');

            $this->assertDatabaseCount('offices', 0);
            $this->assertDatabaseCount('office_audit_events', 0);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_an_unreviewed_file_with_an_existing_code_cannot_be_imported(): void
    {
        Office::create([
            'code' => 'EXISTING',
            'name' => 'Existing Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['NEW-01', 'New Office', 'zonal', 'Active'],
            ['EXISTING', 'Duplicate Office', 'zonal', 'Active'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/offices/import', [
                    'file' => $file,
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('file');

            $this->assertDatabaseCount('offices', 1);

            $this->assertDatabaseMissing('offices', [
                'code' => 'NEW-01',
            ]);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_no_offices_are_imported_if_a_code_is_added_after_preview(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'New Office', 'zonal', 'Active'],
            ['TAKEN-01', 'Another Office', 'zonal', 'Active'],
        ]);

        try {
            // The file is valid when it is reviewed.
            $this->actingAs($admin)
                ->post('/offices/import/validate', [
                    'file' => $file,
                ])
                ->assertOk()
                ->assertSessionHas('office_import_reviewed_hash');

            // Another office is created before the import is submitted.
            Office::create([
                'code' => 'TAKEN-01',
                'name' => 'Office Added After Preview',
                'type' => 'zonal',
                'is_active' => true,
            ]);

            $auditCountBeforeImport = DB::table('office_audit_events')->count();

            $this->actingAs($admin)
                ->post('/offices/import', [
                    'file' => $file,
                ])
                ->assertSessionMissing('import_success');

            // Neither row from the workbook should be imported.
            $this->assertDatabaseCount('offices', 1);

            $this->assertDatabaseMissing('offices', [
                'code' => 'NEW-01',
            ]);

            $this->assertDatabaseHas('offices', [
                'code' => 'TAKEN-01',
                'name' => 'Office Added After Preview',
            ]);

            $this->assertSame(
                $auditCountBeforeImport,
                DB::table('office_audit_events')->count()
            );
        } finally {
            @unlink($file->getPathname());
        }
    }
}
