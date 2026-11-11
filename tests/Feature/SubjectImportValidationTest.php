<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SubjectImportValidationTest extends TestCase
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
        $this->get('/subjects/import')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/subjects/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_without_saving(): void
    {
        $this->office('OFF-01');

        $file = $this->workbook([
            [' off-01 ', ' sub-01 ', 'Test subject', 'Active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/subjects/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('subjects/import')
                ->where('result.total', 1)
                ->where('result.invalid', 0)
                ->where('result.rows.0.office_code', 'OFF-01')
                ->where('result.rows.0.code', 'SUB-01')
            );

        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('subject_audit_events', 0);
    }

    public function test_duplicate_and_invalid_rows_are_reported(): void
    {
        $office = $this->office('OFF-01');
        $this->office('OFF-02');

        Subject::create([
            'office_id' => $office->id,
            'code' => 'EXISTING',
            'name' => 'Existing subject',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['OFF-01', 'EXISTING', 'Existing code', 'active'],
            ['OFF-01', 'NEW-01', 'First occurrence', 'active'],
            ['OFF-01', 'new-01', 'Duplicate', 'inactive'],
            ['OFF-02', 'NEW-01', 'Same code, different office', 'active'],
            ['MISSING', 'NEW-02', 'Unknown office', 'active'],
            ['OFF-01', 'NEW-03', 'Invalid status', 'unknown'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/subjects/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('subjects/import')
                ->where('result.total', 6)
                ->where('result.invalid', 4)
                ->where('result.rows.3.errors', [])
            );

        $this->assertDatabaseCount('subjects', 1);
        $this->assertDatabaseCount('subject_audit_events', 0);
    }

    private function office(string $code): Office
    {
        return Office::create([
            'code' => $code,
            'name' => 'Office '.$code,
            'type' => 'zonal',
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
        $path = tempnam(sys_get_temp_dir(), 'subject_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Office code', 'Subject code', 'Subject name', 'Status'],
            ...$rows,
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'subject-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}