<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AipImportActiveReferencesTest extends TestCase
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

    public function test_inactive_office_is_rejected_during_review_and_save(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        DB::table('offices')->where('id', $office)->update(['is_active' => false]);
        $this->post('/aip-activities/import/validate', $data)->assertSessionHasErrors('office_id');
        DB::table('offices')->where('id', $office)->update(['is_active' => true]);
        $this->post('/aip-activities/import/validate', $data)->assertOk();
        DB::table('offices')->where('id', $office)->update(['is_active' => false]);
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('office_id');
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_review_reports_inactive_vote_fund_subject_and_officer(): void
    {
        $year = $this->year();
        [$office, $officer, $subject] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        DB::table('officers')->where('id', $officer)->update(['is_active' => false]);
        DB::table('subjects')->where('id', $subject)->update(['is_active' => false]);
        DB::table('votes')->where('code', 'V1')->update(['is_active' => false]);
        DB::table('funds')->where('code', 'PF')->update(['is_active' => false]);
        $this->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office,
            'file' => $this->workbook([$this->row('01')]),
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('result.rows.0.errors', 4)
            ->where('result.rows.0.errors.0', 'Vote is inactive.')
            ->where('result.rows.0.errors.1', 'Fund is inactive.')
            ->where('result.rows.0.errors.2', 'Subject is inactive.')
            ->where('result.rows.0.errors.3', 'Officer is inactive.'));
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_each_reference_deactivated_after_review_blocks_save(): void
    {
        $year = $this->year();
        [$office, $officer, $subject] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        foreach ([['officers', 'id', $officer], ['subjects', 'id', $subject], ['votes', 'code', 'V1'], ['funds', 'code', 'PF']] as [$table, $field, $value]) {
            $file = $this->workbook([$this->row('01')]);
            $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
            $this->post('/aip-activities/import/validate', $data)->assertOk();
            DB::table($table)->where($field, $value)->update(['is_active' => false]);
            $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
            $this->assertDatabaseCount('aip_activities', 0);
            $this->assertDatabaseCount('aip_activity_offices', 0);
            $this->assertDatabaseCount('aip_activity_funding_entries', 0);
            $this->assertDatabaseCount('aip_activity_audit_events', 0);
            $this->assertDatabaseCount('aip_activity_office_audit_events', 0);
            $this->assertDatabaseCount('aip_funding_entry_audit_events', 0);
            DB::table($table)->where($field, $value)->update(['is_active' => true]);
        }
    }
}
