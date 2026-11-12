<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OfficerImportSaveTest extends TestCase
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

    public function test_guests_and_regular_users_cannot_import(): void
    {
        $this->post('/officers/import')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post('/officers/import')->assertForbidden();
        $this->assertDatabaseCount('officers', 0);
    }

    public function test_an_administrator_can_import_reviewed_rows_with_audit_events(): void
    {
        [$office, $designation] = $this->references();
        $admin = $this->administrator();
        $file = $this->workbook([
            ['First Officer', 'OFF-01', 'DES-01', 'active'],
            ['Second Officer', 'OFF-01', 'DES-01', 'inactive'],
        ]);
        $this->actingAs($admin)->post('/officers/import/validate', ['file' => $file])->assertOk();
        $this->post('/officers/import', ['file' => $file])->assertRedirect(route('officers.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('officers', ['name' => 'First Officer', 'office_id' => $office->id, 'designation_id' => $designation->id, 'is_active' => true]);
        $this->assertDatabaseHas('officers', ['name' => 'Second Officer', 'office_id' => $office->id, 'designation_id' => $designation->id, 'is_active' => false]);
        $this->assertDatabaseCount('officer_audit_events', 2);
        foreach (['First Officer', 'Second Officer'] as $name) {
            $this->assertDatabaseHas('officer_audit_events', ['officer_id' => Officer::where('name', $name)->firstOrFail()->id, 'actor_id' => $admin->id, 'action' => 'created']);
        }
    }

    public function test_unreviewed_changed_and_invalid_files_cannot_be_imported(): void
    {
        $this->references();
        $admin = $this->administrator();
        $first = $this->workbook([['First Officer', 'OFF-01', 'DES-01', 'active']]);
        $changed = $this->workbook([['Other Officer', 'OFF-01', 'DES-01', 'active']]);
        $invalid = $this->workbook([['123', 'OFF-01', 'DES-01', 'active']]);
        $this->actingAs($admin)->post('/officers/import', ['file' => $first])->assertSessionHasErrors('file');
        $this->post('/officers/import/validate', ['file' => $first])->assertOk();
        $this->post('/officers/import', ['file' => $changed])->assertSessionHasErrors('file');
        $this->post('/officers/import/validate', ['file' => $invalid])->assertOk();
        $this->post('/officers/import', ['file' => $invalid])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('officers', 0);
        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    public function test_warnings_must_be_acknowledged_before_import(): void
    {
        [$office, $designation] = $this->references();
        Officer::create(['name' => 'Same Name', 'office_id' => $office->id, 'designation_id' => $designation->id, 'is_active' => true]);
        $file = $this->workbook([['Same Name', 'OFF-01', 'DES-01', 'active']]);
        $this->actingAs($this->administrator())->post('/officers/import/validate', ['file' => $file])->assertOk();
        $this->post('/officers/import', ['file' => $file])->assertSessionHasErrors('acknowledge_warnings');
        $this->assertDatabaseCount('officers', 1);
        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    public function test_acknowledged_warnings_can_be_imported(): void
    {
        [$office, $designation] = $this->references();
        Officer::create(['name' => 'Same Name', 'office_id' => $office->id, 'designation_id' => $designation->id, 'is_active' => true]);
        $file = $this->workbook([['Same Name', 'OFF-01', 'DES-01', 'active']]);
        $this->actingAs($this->administrator())->post('/officers/import/validate', ['file' => $file])->assertOk();
        $this->post('/officers/import', ['file' => $file, 'acknowledge_warnings' => true])->assertRedirect(route('officers.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('officers', 2);
        $this->assertDatabaseCount('officer_audit_events', 1);
    }

    public function test_new_duplicate_warning_after_review_prevents_all_saves(): void
    {
        [$office, $designation] = $this->references();
        $file = $this->workbook([
            ['First Officer', 'OFF-01', 'DES-01', 'active'],
            ['Second Officer', 'OFF-01', 'DES-01', 'active'],
        ]);
        $this->actingAs($this->administrator())->post('/officers/import/validate', ['file' => $file])->assertOk();
        Officer::create(['name' => 'Second Officer', 'office_id' => $office->id, 'designation_id' => $designation->id, 'is_active' => true]);
        $this->post('/officers/import', ['file' => $file, 'acknowledge_warnings' => true])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('officers', 1);
        $this->assertDatabaseMissing('officers', ['name' => 'First Officer']);
        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    public function test_a_reassigned_office_code_is_rejected_after_review(): void
    {
        [$office] = $this->references();
        $file = $this->workbook([['First Officer', 'OFF-01', 'DES-01', 'active']]);
        $this->actingAs($this->administrator())->post('/officers/import/validate', ['file' => $file])->assertOk();
        $office->update(['code' => 'OFF-OLD']);
        Office::create(['code' => 'OFF-01', 'name' => 'Different Office', 'type' => 'zonal', 'is_active' => true]);
        $this->post('/officers/import', ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('officers', 0);
    }

    private function references(): array
    {
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Test Office', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Test Designation', 'is_active' => true]);
        return [$office, $designation];
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'officer_import_');
        $this->assertNotFalse($path);
        $this->temporaryFiles[] = $path;
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Officer name', 'Office code', 'Designation code', 'Status'], ...$rows,
        ]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return new UploadedFile($path, 'officer-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
