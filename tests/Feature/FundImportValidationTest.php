<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FundImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->fromArray([
            ['Fund code', 'Fund name', 'Status'],
            ...$rows,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'fund-import-');

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'funds.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_a_guest_cannot_open_the_import_page(): void
    {
        $this->get('/funds/import')->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/funds/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_without_saving(): void
    {
        $file = $this->workbook([
            [' fund-01 ', ' Example Fund ', 'Active'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/funds/import/validate', ['file' => $file])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('funds/import')
                    ->where('result.total', 1)
                    ->where('result.invalid', 0)
                    ->where('result.rows.0.code', 'FUND-01')
                    ->where('result.rows.0.name', 'Example Fund')
                    ->where('result.rows.0.status', 'active')
                );

            $this->assertDatabaseCount('funds', 0);
            $this->assertDatabaseCount('fund_audit_events', 0);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_duplicate_and_invalid_rows_are_reported(): void
    {
        Fund::create([
            'code' => 'EXISTING',
            'name' => 'Existing Fund',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['EXISTING', 'Existing Code', 'Active'],
            ['NEW-01', 'First Fund', 'Active'],
            ['new-01', 'Second Fund', 'wrong'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/funds/import/validate', ['file' => $file])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('funds/import')
                    ->where('result.total', 3)
                    ->where('result.invalid', 2)
                    ->has('result.rows.0.errors', 1)
                    ->has('result.rows.2.errors', 2)
                );

            $this->assertDatabaseCount('funds', 1);
            $this->assertDatabaseCount('fund_audit_events', 0);
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_a_formula_in_an_import_row_is_rejected(): void
    {
        $file = $this->workbook([
            ['FUND-01', '=1+1', 'Active'],
        ]);

        try {
            $this->actingAs($this->administrator())
                ->post('/funds/import/validate', ['file' => $file])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('funds/import')
                    ->where('result.invalid', 1)
                    ->has('result.rows.0.errors')
                );

            $this->assertDatabaseCount('funds', 0);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
