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

    public function test_no_offices_are_saved_when_any_row_is_invalid(): void
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
                ->assertOk();

            $this->assertDatabaseCount('offices', 0);
            $this->assertDatabaseCount('office_audit_events', 0);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_no_offices_are_saved_when_a_code_already_exists(): void
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
                ->assertOk();

            $this->assertDatabaseCount('offices', 1);
            $this->assertDatabaseMissing('offices', [
                'code' => 'NEW-01',
            ]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
