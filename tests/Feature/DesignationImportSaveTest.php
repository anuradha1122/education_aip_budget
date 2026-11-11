<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DesignationImportSaveTest extends TestCase
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

    public function test_a_guest_cannot_import_designations(): void
    {
        $this->post('/designations/import')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('designations', 0);
    }

    public function test_a_regular_user_cannot_import_designations(): void
    {
        $user = User::factory()->create();

        $file = $this->workbook([
            ['NEW-01', 'New designation', 'active'],
        ]);

        $this->actingAs($user)
            ->post('/designations/import', ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('designations', 0);
        $this->assertDatabaseCount('designation_audit_events', 0);
    }

    public function test_an_administrator_can_import_reviewed_designations_with_audit_events(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['designation-01', 'First designation', 'active'],
            ['designation-02', 'Second designation', 'inactive'],
        ]);

        $this->actingAs($admin)
            ->post('/designations/import/validate', ['file' => $file])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/designations/import', ['file' => $file])
            ->assertRedirect(route('designations.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('designations', [
            'code' => 'DESIGNATION-01',
            'name' => 'First designation',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('designations', [
            'code' => 'DESIGNATION-02',
            'name' => 'Second designation',
            'is_active' => false,
        ]);

        $firstDesignation = Designation::query()->where('code', 'DESIGNATION-01')->firstOrFail();
        $secondDesignation = Designation::query()->where('code', 'DESIGNATION-02')->firstOrFail();

        $this->assertDatabaseCount('designations', 2);
        $this->assertDatabaseCount('designation_audit_events', 2);

        $this->assertDatabaseHas('designation_audit_events', [
            'designation_id' => $firstDesignation->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas('designation_audit_events', [
            'designation_id' => $secondDesignation->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_unreviewed_file_cannot_be_imported(): void
    {
        $file = $this->workbook([
            ['NEW-01', 'New designation', 'active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/designations/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('designations', 0);
        $this->assertDatabaseCount('designation_audit_events', 0);
    }

    public function test_a_different_file_cannot_be_imported_after_review(): void
    {
        $admin = $this->administrator();

        $reviewedFile = $this->workbook([
            ['NEW-01', 'First designation', 'active'],
        ]);

        $differentFile = $this->workbook([
            ['NEW-02', 'Second designation', 'active'],
        ]);

        $this->actingAs($admin)
            ->post('/designations/import/validate', ['file' => $reviewedFile])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/designations/import', ['file' => $differentFile])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('designations', 0);
        $this->assertDatabaseCount('designation_audit_events', 0);
    }

    public function test_an_invalid_workbook_cannot_be_imported(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'Valid designation', 'active'],
            ['NEW-02', 'Invalid designation', 'unknown'],
        ]);

        $this->actingAs($admin)
            ->post('/designations/import/validate', ['file' => $file])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/designations/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('designations', 0);
        $this->assertDatabaseCount('designation_audit_events', 0);
    }

    public function test_no_designations_are_imported_if_a_code_is_added_after_review(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'First designation', 'active'],
            ['NEW-02', 'Second designation', 'active'],
        ]);

        $this->actingAs($admin)
            ->post('/designations/import/validate', ['file' => $file])
            ->assertOk();

        Designation::create([
            'code' => 'NEW-02',
            'name' => 'Existing designation',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/designations/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('designations', 1);

        $this->assertDatabaseMissing('designations', [
            'code' => 'NEW-01',
        ]);

        $this->assertDatabaseCount('designation_audit_events', 0);
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
        $path = tempnam(sys_get_temp_dir(), 'designation_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->fromArray([
            ['Designation code', 'Designation name', 'Status'],
            ...$rows,
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'designation-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}