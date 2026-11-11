<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SubjectImportSaveTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) unlink($path);
        }
        parent::tearDown();
    }

    public function test_guests_and_regular_users_cannot_import(): void
    {
        $this->post('/subjects/import')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->post('/subjects/import')->assertForbidden();
        $this->assertDatabaseCount('subjects', 0);
    }

    public function test_administrator_can_import_reviewed_subjects_with_audit_events(): void
    {
        $admin = $this->administrator();
        $office = $this->office();
        $file = $this->workbook([
            ['OFF-01', 'sub-01', 'Science', 'active'],
            ['OFF-01', 'sub-02', 'Maths', 'inactive'],
        ]);
        $this->actingAs($admin)->post('/subjects/import/validate', ['file' => $file])->assertOk();
        $this->post('/subjects/import', ['file' => $file])
            ->assertRedirect(route('subjects.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subjects', ['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Science', 'is_active' => true]);
        $this->assertDatabaseHas('subjects', ['office_id' => $office->id, 'code' => 'SUB-02', 'name' => 'Maths', 'is_active' => false]);
        $this->assertDatabaseCount('subject_audit_events', 2);
        foreach (['SUB-01', 'SUB-02'] as $code) {
            $subject = Subject::where('code', $code)->firstOrFail();
            $this->assertDatabaseHas('subject_audit_events', [
                'subject_id' => $subject->id, 'actor_id' => $admin->id, 'action' => 'created',
            ]);
        }
    }

    public function test_unreviewed_and_changed_files_cannot_be_imported(): void
    {
        $this->office();
        $admin = $this->administrator();
        $first = $this->workbook([['OFF-01', 'NEW-01', 'Science', 'active']]);
        $second = $this->workbook([['OFF-01', 'NEW-02', 'Maths', 'active']]);
        $this->actingAs($admin)->post('/subjects/import', ['file' => $first])->assertSessionHasErrors('file');
        $this->post('/subjects/import/validate', ['file' => $first])->assertOk();
        $this->post('/subjects/import', ['file' => $second])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('subject_audit_events', 0);
    }

    public function test_invalid_workbook_cannot_be_imported(): void
    {
        $this->office();
        $file = $this->workbook([
            ['OFF-01', 'NEW-01', 'Science', 'active'],
            ['OFF-01', 'NEW-02', 'Maths', 'invalid'],
        ]);
        $this->actingAs($this->administrator())->post('/subjects/import/validate', ['file' => $file])->assertOk();
        $this->post('/subjects/import', ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('subject_audit_events', 0);
    }

    public function test_no_subjects_are_imported_if_a_code_is_added_after_review(): void
    {
        $office = $this->office();
        $file = $this->workbook([
            ['OFF-01', 'NEW-01', 'Science', 'active'],
            ['OFF-01', 'NEW-02', 'Maths', 'active'],
        ]);
        $this->actingAs($this->administrator())->post('/subjects/import/validate', ['file' => $file])->assertOk();
        Subject::create(['office_id' => $office->id, 'code' => 'NEW-02', 'name' => 'Existing', 'is_active' => true]);
        $this->post('/subjects/import', ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('subjects', 1);
        $this->assertDatabaseMissing('subjects', ['office_id' => $office->id, 'code' => 'NEW-01']);
        $this->assertDatabaseCount('subject_audit_events', 0);
    }

    public function test_a_changed_office_code_cannot_redirect_an_import_to_another_office(): void
    {
        $office = $this->office();
        $file = $this->workbook([['OFF-01', 'NEW-01', 'Science', 'active']]);
        $this->actingAs($this->administrator())->post('/subjects/import/validate', ['file' => $file])->assertOk();
        $office->update(['code' => 'OFF-OLD']);
        $this->office();
        $this->post('/subjects/import', ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('subjects', 0);
    }

    private function office(): Office
    {
        return Office::create(['code' => 'OFF-01', 'name' => 'Test office', 'type' => 'zonal', 'is_active' => true]);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'subject_import_');
        $this->assertNotFalse($path);
        $this->temporaryFiles[] = $path;
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Office code', 'Subject code', 'Subject name', 'Status'], ...$rows,
        ]);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return new UploadedFile($path, 'subject-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
