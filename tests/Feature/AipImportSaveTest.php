<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AipImportSaveTest extends TestCase
{
    use RefreshDatabase;

    private function year(): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function office(string $code): array
    {
        $office = DB::table('offices')->insertGetId([
            'code' => 'OFF-'.$code, 'name' => 'Office '.$code, 'type' => 'zonal',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $designation = DB::table('designations')->insertGetId([
            'code' => 'DES-'.$code, 'name' => 'Director '.$code, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $officer = DB::table('officers')->insertGetId([
            'office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer '.$code,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subject = DB::table('subjects')->insertGetId([
            'office_id' => $office, 'code' => 'SCI-'.$code, 'name' => 'Science',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer, 'subject_id' => $subject]);
        return [$office, $officer, $subject];
    }

    private function masterFunding(): void
    {
        DB::table('votes')->insert(['code' => 'V1', 'description' => 'Vote', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('funds')->insert(['code' => 'PF', 'name' => 'Fund', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin(bool $allowed = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true, 'can_import_aip' => $allowed])->save();
        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Serial number', 'Level', 'Activity Number', 'Activity', 'Subject code', 'Subject', 'Officer', 'Designation', 'Vote', 'Fund'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }
        $path = tempnam(sys_get_temp_dir(), 'aip-save-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return new UploadedFile($path, 'aip.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function row(string $code, string $number = '123', string $level = 'Zonal'): array
    {
        return [1, $level, $number, 'Science seminar', 'SCI-'.$code, 'Science', 'Officer '.$code, 'Director '.$code, 'V1', 'PF'];
    }

    public function test_save_reuses_common_activity_for_second_office_and_audits_writes(): void
    {
        $year = $this->year();
        [$office1, $officer1, $subject1] = $this->office('01');
        [$office2, $officer2, $subject2] = $this->office('02');
        $this->masterFunding();
        $actor = $this->admin();
        $this->actingAs($actor);
        foreach ([[$office1, '01'], [$office2, '02']] as [$office, $code]) {
            $file = $this->workbook([$this->row($code)]);
            $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
            $this->post('/aip-activities/import/validate', $data)->assertOk();
            $this->post('/aip-activities/import/save', $data)->assertRedirect(route('aip-activities.index'))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('aip_activities', 1);
        $this->assertDatabaseCount('aip_activity_offices', 2);
        $this->assertDatabaseCount('aip_activity_funding_entries', 1);
        $this->assertDatabaseCount('aip_activity_audit_events', 1);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 2);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 1);
        $activity = DB::table('aip_activities')->first();
        $this->assertDatabaseHas('aip_activity_offices', ['aip_activity_id' => $activity->id, 'office_id' => $office1, 'officer_id' => $officer1, 'subject_id' => $subject1]);
        $this->assertDatabaseHas('aip_activity_offices', ['aip_activity_id' => $activity->id, 'office_id' => $office2, 'officer_id' => $officer2, 'subject_id' => $subject2]);
        $this->assertDatabaseHas('aip_activity_audit_events', ['aip_activity_id' => $activity->id, 'actor_id' => $actor->id]);
    }

    public function test_existing_office_cannot_be_imported_twice(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        $this->post('/aip-activities/import/save', $data)->assertSessionHasNoErrors();
        // A consumed review cannot be replayed, and a fresh review finds the link.
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 1);
        $this->assertDatabaseCount('aip_activity_offices', 1);
        $this->assertDatabaseCount('aip_activity_funding_entries', 1);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 1);
    }

    public function test_save_requires_permission_and_a_review_of_the_same_file_office_and_year(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        [$other] = $this->office('02');
        $this->masterFunding();
        $file = $this->workbook([$this->row('01')]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/save', $data)->assertRedirect(route('login'));
        $this->actingAs($this->admin(false))->post('/aip-activities/import/save', $data)->assertForbidden();
        $this->actingAs($this->admin())->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        $changed = $this->workbook([$this->row('01', '999')]);
        $this->post('/aip-activities/import/save', array_merge($data, ['file' => $changed]))->assertSessionHasErrors('file');
        $this->post('/aip-activities/import/save', array_merge($data, ['office_id' => $other]))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
    }

    public function test_save_revalidates_a_changed_assignment_and_closed_year(): void
    {
        $year = $this->year();
        [$office, $officer, $subject] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        $assignment = DB::table('officer_subject_assignments')->where('officer_id', $officer)->where('subject_id', $subject)->value('id');
        app(OfficerSubjectAssignmentService::class)->end($assignment);
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 0);
        DB::table('financial_years')->where('id', $year)->update(['is_closed' => true]);
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('financial_year_id');
        $this->assertDatabaseCount('aip_activity_offices', 0);
    }

    public function test_new_conflicts_after_review_block_save_without_import_writes(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        DB::table('aip_activities')->insert([
            'financial_year_id' => $year, 'activity_number' => '123',
            'description' => 'Different activity', 'level' => 'Zonal',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 1);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
        $this->assertDatabaseCount('aip_activity_audit_events', 0);
    }

    public function test_an_error_in_a_later_row_rolls_back_prior_writes(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01', '123'), $this->row('01', '124')]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        $this->app->instance(\App\Services\AipActivityOfficeService::class, new class extends \App\Services\AipActivityOfficeService {
            private int $calls = 0;

            public function create(array $input, ?User $actor = null): int
            {
                if (++$this->calls === 2) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['office_id' => 'Simulated failure.']);
                }
                return parent::create($input, $actor);
            }
        });
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('office_id');
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
        $this->assertDatabaseCount('aip_activity_audit_events', 0);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 0);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 0);
    }
}
