<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProgrammeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgrammeTypeImportValidationTest extends TestCase
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
        $this->get('/programme-types/import')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/programme-types/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_without_saving(): void
    {
        $file = $this->workbook([
            [' programme-01 ', 'Test programme type', 'Active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/programme-types/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('programme-types/import')
                ->where('result.total', 1)
                ->where('result.invalid', 0)
                ->where('result.rows.0.code', 'PROGRAMME-01')
            );

        $this->assertDatabaseCount('programme_types', 0);
        $this->assertDatabaseCount('programme_type_audit_events', 0);
    }

    public function test_duplicate_and_invalid_rows_are_reported(): void
    {
        ProgrammeType::create([
            'code' => 'EXISTING',
            'name' => 'Existing programme type',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['EXISTING', 'Existing code', 'active'],
            ['NEW-01', 'First occurrence', 'active'],
            ['new-01', 'Duplicate', 'inactive'],
            ['NEW-02', 'Invalid status', 'unknown'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/programme-types/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('programme-types/import')
                ->where('result.total', 4)
                ->where('result.invalid', 3)
            );

        $this->assertDatabaseCount('programme_types', 1);
        $this->assertDatabaseCount('programme_type_audit_events', 0);
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
        $path = tempnam(sys_get_temp_dir(), 'programme type_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Programme type code', 'Programme type name', 'Status'],
            ...$rows,
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'programme-type-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}

