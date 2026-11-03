<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OfficeImportValidationTest extends TestCase
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
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Office code', 'Office name', 'Office type', 'Status'],
            ...$rows,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'office-import-');

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

    public function test_a_guest_cannot_open_the_import_page(): void
    {
        $this->get('/offices/import')->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/offices/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_a_valid_row_without_saving(): void
    {
        $file = $this->workbook([
            ['ZONE-01', 'Example Office', 'zonal', 'Active'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/offices/import/validate', [
                    'file' => $file,
                ])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('offices/import')
                    ->where('result.total', 1)
                    ->where('result.invalid', 0)
                    ->where('result.rows.0.code', 'ZONE-01')
                );

            $this->assertDatabaseCount('offices', 0);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_duplicate_and_invalid_rows_are_reported(): void
    {
        Office::create([
            'code' => 'EXISTING',
            'name' => 'Existing Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['EXISTING', 'Existing Code', 'zonal', 'Active'],
            ['NEW-01', 'First Office', 'zonal', 'Active'],
            ['new-01', 'Second Office', 'invalid', 'Active'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/offices/import/validate', [
                    'file' => $file,
                ])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('offices/import')
                    ->where('result.total', 3)
                    ->where('result.invalid', 2)
                    ->has('result.rows.0.errors', 1)
                    ->has('result.rows.2.errors', 2)
                );

            $this->assertDatabaseCount('offices', 1);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
