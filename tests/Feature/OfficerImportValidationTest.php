<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OfficerImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_a_guest_cannot_open_the_import_page(): void
    {
        $this->get('/officers/import')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/officers/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_without_saving(): void
    {
        $this->office();
        $this->designation();

        $file = $this->workbook([
            [' Test Officer ', ' off-01 ', ' des-01 ', 'Active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/officers/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officers/import')
                ->where('result.total', 1)
                ->where('result.invalid', 0)
                ->where('result.warnings', 0)
                ->where('result.rows.0.name', 'Test Officer')
                ->where('result.rows.0.office_code', 'OFF-01')
                ->where('result.rows.0.designation_code', 'DES-01')
            );

        $this->assertDatabaseCount('officers', 0);
        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    public function test_invalid_rows_and_possible_duplicates_are_reported(): void
    {
        $office = $this->office();
        $designation = $this->designation();

        Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Existing Officer',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['Existing Officer', 'OFF-01', 'DES-01', 'active'],
            ['New Officer', 'OFF-01', 'DES-01', 'active'],
            ['new officer', 'OFF-01', 'DES-01', 'inactive'],
            ['Invalid Officer', 'UNKNOWN', 'DES-01', 'unknown'],
            ['Another Officer', 'OFF-01', 'UNKNOWN', 'active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/officers/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officers/import')
                ->where('result.total', 5)
                ->where('result.invalid', 2)
                ->where('result.warnings', 2)
                ->has('result.rows.0.warnings', 1)
                ->has('result.rows.2.warnings', 1)
                ->has('result.rows.3.errors', 2)
                ->has('result.rows.4.errors', 1)
            );

        $this->assertDatabaseCount('officers', 1);
        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    public function test_a_name_containing_only_numbers_is_invalid(): void
    {
        $this->office();
        $this->designation();

        $file = $this->workbook([
            ['123', 'OFF-01', 'DES-01', 'active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/officers/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officers/import')
                ->where('result.total', 1)
                ->where('result.invalid', 1)
                ->has('result.rows.0.errors', 1)
            );

        $this->assertDatabaseCount('officers', 0);
    }

    private function office(): Office
    {
        return Office::create([
            'code' => 'OFF-01',
            'name' => 'Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    private function designation(): Designation
    {
        return Designation::create([
            'code' => 'DES-01',
            'name' => 'Test Designation',
            'is_active' => true,
        ]);
    }

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
        $path = tempnam(sys_get_temp_dir(), 'officer_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->fromArray([
            ['Officer name', 'Office code', 'Designation code', 'Status'],
            ...$rows,
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'officer-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}