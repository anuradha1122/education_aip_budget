<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProgrammeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgrammeTypeImportSaveTest extends TestCase
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

    public function test_a_guest_cannot_import_programme_types(): void
    {
        $this->post('/programme-types/import')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('programme_types', 0);
    }

    public function test_a_regular_user_cannot_import_programme_types(): void
    {
        $user = User::factory()->create();

        $file = $this->workbook([
            ['NEW-01', 'New programme type', 'active'],
        ]);

        $this->actingAs($user)
            ->post('/programme-types/import', ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('programme_types', 0);
        $this->assertDatabaseCount('programme_type_audit_events', 0);
    }

    public function test_an_administrator_can_import_reviewed_programme_types_with_audit_events(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['programme-01', 'First programme type', 'active'],
            ['programme-02', 'Second programme type', 'inactive'],
        ]);

        $this->actingAs($admin)
            ->post('/programme-types/import/validate', ['file' => $file])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/programme-types/import', ['file' => $file])
            ->assertRedirect(route('programme-types.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('programme_types', [
            'code' => 'PROGRAMME-01',
            'name' => 'First programme type',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('programme_types', [
            'code' => 'PROGRAMME-02',
            'name' => 'Second programme type',
            'is_active' => false,
        ]);

        $firstProgrammeType = ProgrammeType::query()->where('code', 'PROGRAMME-01')->firstOrFail();
        $secondProgrammeType = ProgrammeType::query()->where('code', 'PROGRAMME-02')->firstOrFail();

        $this->assertDatabaseCount('programme_types', 2);
        $this->assertDatabaseCount('programme_type_audit_events', 2);

        $this->assertDatabaseHas('programme_type_audit_events', [
            'programme_type_id' => $firstProgrammeType->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas('programme_type_audit_events', [
            'programme_type_id' => $secondProgrammeType->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_unreviewed_file_cannot_be_imported(): void
    {
        $file = $this->workbook([
            ['NEW-01', 'New programme type', 'active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/programme-types/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('programme_types', 0);
        $this->assertDatabaseCount('programme_type_audit_events', 0);
    }

    public function test_a_different_file_cannot_be_imported_after_review(): void
    {
        $admin = $this->administrator();

        $reviewedFile = $this->workbook([
            ['NEW-01', 'First programme type', 'active'],
        ]);

        $differentFile = $this->workbook([
            ['NEW-02', 'Second programme type', 'active'],
        ]);

        $this->actingAs($admin)
            ->post('/programme-types/import/validate', ['file' => $reviewedFile])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/programme-types/import', ['file' => $differentFile])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('programme_types', 0);
        $this->assertDatabaseCount('programme_type_audit_events', 0);
    }

    public function test_an_invalid_workbook_cannot_be_imported(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'Valid programme type', 'active'],
            ['NEW-02', 'Invalid programme type', 'unknown'],
        ]);

        $this->actingAs($admin)
            ->post('/programme-types/import/validate', ['file' => $file])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/programme-types/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('programme_types', 0);
        $this->assertDatabaseCount('programme_type_audit_events', 0);
    }

    public function test_no_programme_types_are_imported_if_a_code_is_added_after_review(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'First programme type', 'active'],
            ['NEW-02', 'Second programme type', 'active'],
        ]);

        $this->actingAs($admin)
            ->post('/programme-types/import/validate', ['file' => $file])
            ->assertOk();

        ProgrammeType::create([
            'code' => 'NEW-02',
            'name' => 'Existing programme type',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/programme-types/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('programme_types', 1);

        $this->assertDatabaseMissing('programme_types', [
            'code' => 'NEW-01',
        ]);

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

        $spreadsheet->getActiveSheet()->fromArray([
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