<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AipImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private function year(bool $closed = false): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_closed' => $closed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function office(): int
    {
        return DB::table('offices')->insertGetId([
            'code' => 'OFFICE-01', 'name' => 'Example office', 'type' => 'zonal',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true, 'can_import_aip' => true])->save();

        return $user;
    }

    private function workbook(array $rows, bool $validHeaders = true): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            $validHeaders ? 'Serial number' : 'Wrong heading',
            'Level',
            'Activity Number',
            'Activity',
            'Subject code',
            'Subject',
            'Officer',
            'Designation',
            'Vote',
            'Fund',
        ], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $path = tempnam(sys_get_temp_dir(), 'aip-test-');

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'aip.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_guests_and_regular_users_cannot_validate(): void
    {
        $this->get('/aip-activities/import')
            ->assertRedirect(route('login'));

        $this->post('/aip-activities/import/validate', [])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get('/aip-activities/import')->assertForbidden();
        $this->post('/aip-activities/import/validate', [])->assertForbidden();
    }

    public function test_admin_can_review_without_saving(): void
    {
        $year = $this->year();
        $office = $this->office();
        DB::table('subjects')->insert(['office_id' => $office, 'code' => 'SCI', 'name' => 'Science', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $designationId = DB::table('designations')->insertGetId(['code' => 'DES-01', 'name' => 'Designation', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('officers')->insert(['office_id' => $office, 'designation_id' => $designationId, 'name' => 'Officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('officer_subject_assignments')->insert([
            'officer_id' => DB::table('officers')->where('office_id', $office)->where('name', 'Officer')->value('id'),
            'subject_id' => DB::table('subjects')->where('office_id', $office)->where('code', 'SCI')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('votes')->insert(['code' => 'VOTE-01', 'description' => 'Example vote', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('funds')->insert(['code' => 'PF', 'name' => 'Example fund', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $file = $this->workbook([
            [1, 'Zonal', '123', 'Science seminar', 'SCI', 'Science',
                'Officer', 'Designation', 'VOTE-01', 'PF'],
        ]);

        $this->actingAs($this->admin())
            ->post('/aip-activities/import/validate', [
                'financial_year_id' => $year,
                'office_id' => $office,
                'file' => $file,
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('aip-activities/import')
                ->where('result.financial_year', 2027)
                ->where('result.office.code', 'OFFICE-01')
                ->where('result.office.name', 'Example office')
                ->where('result.rows.0.activity_number', '123')
                ->where('result.rows.0.fund', 'PF')
                ->has('result.rows.0.errors', 0));

        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_duplicate_and_invalid_activity_numbers_are_reported(): void
    {
        $year = $this->year();
        $office = $this->office();

        $file = $this->workbook([
            [1, 'Zonal', '123', 'First activity'],
            [2, 'Zonal', '123', 'Second activity'],
            [3, 'School', 'x1', 'Invalid activity'],
        ]);

        $this->actingAs($this->admin())
            ->post('/aip-activities/import/validate', [
                'financial_year_id' => $year,
                'office_id' => $office,
                'file' => $file,
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('result.rows.0.errors', 5)
                ->has('result.rows.1.errors', 5)
                ->has('result.rows.2.errors', 5));

        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_a_closed_year_is_rejected(): void
    {
        $year = $this->year(true);
        $office = $this->office();
        $file = $this->workbook([[1, 'Zonal', '123', 'Example']], false);

        $this->actingAs($this->admin())
            ->post('/aip-activities/import/validate', [
                'financial_year_id' => $year,
                'office_id' => $office,
                'file' => $file,
            ])
            ->assertSessionHasErrors('financial_year_id');

        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_wrong_template_headers_are_rejected(): void
    {
        $year = $this->year();
        $office = $this->office();

        $file = $this->workbook([
            [1, 'Zonal', '123', 'Example activity'],
        ], false);

        $this->actingAs($this->admin())
            ->post('/aip-activities/import/validate', [
                'financial_year_id' => $year,
                'office_id' => $office,
                'file' => $file,
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }
    public function test_an_office_is_required_for_validation(): void
    {
        $year = $this->year();
        $file = $this->workbook([[1, 'Zonal', '123', 'Example']]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'file' => $file,
        ])->assertSessionHasErrors('office_id');
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_existing_activities_are_reviewed_for_the_selected_office(): void
    {
        $year = $this->year();
        $office = $this->office();
        $first = DB::table('aip_activities')->insertGetId([
            'financial_year_id' => $year, 'activity_number' => '123',
            'description' => 'Science seminar', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('aip_activities')->insert([
            'financial_year_id' => $year, 'activity_number' => '124',
            'description' => 'Existing description', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('aip_activity_offices')->insert([
            'aip_activity_id' => $first, 'office_id' => $office,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $file = $this->workbook([
            [1, 'Zonal', '123', 'Science seminar'],
            [2, 'Zonal', '124', 'Different description'],
            [3, 'Zonal', '125', 'New activity'],
        ]);

        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.0', 'Activity already exists for this financial year; review its existing details before importing.')
            ->where('result.rows.0.errors.1', 'This activity is already linked to the selected office.')
            ->where('result.rows.1.errors.1', 'The activity description differs from the existing activity.')
            ->has('result.rows.2.errors', 4));

        $this->assertDatabaseCount('aip_activities', 2);
        $this->assertDatabaseCount('aip_activity_offices', 1);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_supplied_subject_codes_are_checked_against_selected_office(): void
    {
        $year = $this->year();
        $office = $this->office();
        $otherOffice = DB::table('offices')->insertGetId([
            'code' => 'OFFICE-02', 'name' => 'Other office', 'type' => 'zonal',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$office, 'SCI'], [$otherOffice, 'MATH']] as [$officeId, $code]) {
            DB::table('subjects')->insert([
                'office_id' => $officeId, 'code' => $code, 'name' => 'Subject '.$code,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $file = $this->workbook([
            [1, 'Zonal', '101', 'First', 'SCI'],
            [2, 'Zonal', '102', 'Second', 'MATH'],
            [3, 'Zonal', '103', 'Third', 'UNKNOWN'],
            [4, 'Zonal', '104', 'Fourth', ''],
        ]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('result.rows.0.errors', 3)
            ->where('result.rows.1.errors.0', 'Subject code was not found for the selected office.')
            ->where('result.rows.2.errors.0', 'Subject code was not found for the selected office.')
            ->has('result.rows.3.errors', 4));
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_officer_and_designation_are_checked_for_the_selected_office(): void
    {
        $year = $this->year();
        $office = $this->office();
        $otherOffice = DB::table('offices')->insertGetId([
            'code' => 'OFFICE-02', 'name' => 'Other office', 'type' => 'zonal',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $designation = DB::table('designations')->insertGetId([
            'code' => 'DIR', 'name' => 'Director', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$office, 'Officer One'], [$otherOffice, 'Officer Two']] as [$officeId, $name]) {
            DB::table('officers')->insert([
                'office_id' => $officeId, 'designation_id' => $designation,
                'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $file = $this->workbook([
            [1, 'Zonal', '101', 'First', '', '', 'Officer One', 'Director'],
            [2, 'Zonal', '102', 'Second', '', '', 'Officer Two', 'Director'],
            [3, 'Zonal', '103', 'Third', '', '', 'Officer One', 'Teacher'],
            [4, 'Zonal', '104', 'Fourth', '', '', '', 'Director'],
            [5, 'Zonal', '105', 'Fifth', '', '', '', ''],
        ]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('result.rows.0.errors', 3)
            ->where('result.rows.1.errors.0', 'Officer name was not found for the selected office.')
            ->where('result.rows.2.errors.0', 'Designation does not match the selected officer.')
            ->where('result.rows.3.errors.0', 'Designation cannot be checked without an officer name.')
            ->has('result.rows.4.errors', 4));
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_review_checks_active_officer_subject_assignments(): void
    {
        $year = $this->year();
        $office = $this->office();
        $designation = DB::table('designations')->insertGetId([
            'code' => 'DIR', 'name' => 'Director', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $officer = DB::table('officers')->insertGetId([
            'office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer One',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjects = [];
        foreach (['ACTIVE', 'ENDED', 'NONE'] as $code) {
            $subjects[$code] = DB::table('subjects')->insertGetId([
                'office_id' => $office, 'code' => $code, 'name' => 'Subject '.$code,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $service = app(\App\Services\OfficerSubjectAssignmentService::class);
        $service->assign(['officer_id' => $officer, 'subject_id' => $subjects['ACTIVE']]);
        $ended = $service->assign(['officer_id' => $officer, 'subject_id' => $subjects['ENDED']]);
        $service->end($ended);
        $file = $this->workbook([
            [1, 'Zonal', '101', 'First', 'ACTIVE', '', 'Officer One', 'Director'],
            [2, 'Zonal', '102', 'Second', 'ENDED', '', 'Officer One', 'Director'],
            [3, 'Zonal', '103', 'Third', 'NONE', '', 'Officer One', 'Director'],
            [4, 'Zonal', '104', 'Fourth', 'UNKNOWN', '', 'Officer One', 'Director'],
            [5, 'Zonal', '105', 'Fifth', 'ACTIVE', '', '', ''],
        ]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('result.rows.0.errors', 2)
            ->where('result.rows.1.errors.0', 'Officer does not have an active assignment to this subject.')
            ->where('result.rows.2.errors.0', 'Officer does not have an active assignment to this subject.')
            ->where('result.rows.3.errors.0', 'Subject code was not found for the selected office.')
            ->has('result.rows.3.errors', 3)
            ->has('result.rows.4.errors', 3));
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
        $this->assertDatabaseCount('officer_subject_assignments', 2);
    }

    public function test_existing_activity_fund_conflicts_are_reported_without_saving(): void
    {
        $year = $this->year();
        $office = $this->office();
        $fund = DB::table('funds')->insertGetId([
            'code' => 'PF', 'name' => 'Provincial fund', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('funds')->insert([
            'code' => 'WB', 'name' => 'Other fund', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $vote = DB::table('votes')->insertGetId([
            'code' => 'V1', 'description' => 'Example vote', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['123', '124'] as $number) {
            $activity = DB::table('aip_activities')->insertGetId([
                'financial_year_id' => $year, 'activity_number' => $number,
                'description' => 'Science seminar', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('aip_activity_funding_entries')->insert([
                'aip_activity_id' => $activity, 'vote_id' => $vote, 'fund_id' => $fund,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $file = $this->workbook([
            [1, 'Zonal', '123', 'Science seminar', '', '', '', '', 'V1', 'WB'],
            [2, 'Zonal', '124', 'Science seminar', '', '', '', '', 'V1', 'PF'],
            [3, 'Zonal', '125', 'New activity', '', '', '', '', 'V1', 'WB'],
        ]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.1', 'Fund differs from the existing fund for this activity.')
            ->has('result.rows.1.errors', 3)
            ->has('result.rows.2.errors', 2));
        $this->assertDatabaseCount('aip_activities', 2);
        $this->assertDatabaseCount('aip_activity_funding_entries', 2);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 0);
    }

    public function test_existing_activity_vote_conflicts_are_reported_without_saving(): void
    {
        $year = $this->year();
        $office = $this->office();
        $fund = DB::table('funds')->insertGetId([
            'code' => 'PF', 'name' => 'Provincial fund', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $vote = DB::table('votes')->insertGetId([
            'code' => 'V1', 'description' => 'First vote', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('votes')->insert([
            'code' => 'V2', 'description' => 'Second vote', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['123', '124', '126'] as $number) {
            $activity = DB::table('aip_activities')->insertGetId([
                'financial_year_id' => $year, 'activity_number' => $number,
                'description' => 'Science seminar', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($number !== '126') {
                DB::table('aip_activity_funding_entries')->insert([
                    'aip_activity_id' => $activity, 'vote_id' => $vote, 'fund_id' => $fund,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $file = $this->workbook([
            [1, 'Zonal', '123', 'Science seminar', '', '', '', '', 'V2', 'PF'],
            [2, 'Zonal', '124', 'Science seminar', '', '', '', '', 'V1', 'PF'],
            [3, 'Zonal', '125', 'New activity', '', '', '', '', 'V2', 'PF'],
            [4, 'Zonal', '126', 'Science seminar', '', '', '', '', 'V2', 'PF'],
        ]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.1', 'Vote differs from the existing vote for this activity.')
            ->has('result.rows.1.errors', 3)
            ->has('result.rows.2.errors', 2)
            ->has('result.rows.3.errors', 3));
        $this->assertDatabaseCount('aip_activities', 3);
        $this->assertDatabaseCount('aip_activity_funding_entries', 2);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 0);
    }

    public function test_subject_officer_vote_and_fund_are_required(): void
    {
        $year = $this->year();
        $office = $this->office();
        $file = $this->workbook([[1, 'Zonal', '501', 'Example activity']]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('result.rows.0.errors', 4)
            ->where('result.rows.0.errors.0', 'Subject code is required.')
            ->where('result.rows.0.errors.1', 'Officer is required.')
            ->where('result.rows.0.errors.2', 'Vote is required.')
            ->where('result.rows.0.errors.3', 'Fund is required.'));
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_unknown_vote_and_fund_codes_are_reported(): void
    {
        $year = $this->year();
        $office = $this->office();
        $file = $this->workbook([[1, 'Zonal', '123', 'Science seminar', 'SCI', 'Science', 'Officer', 'Designation', 'UNKNOWN-VOTE', 'UNKNOWN-FUND']]);
        $this->actingAs($this->admin())->post('/aip-activities/import/validate', [
            'financial_year_id' => $year, 'office_id' => $office, 'file' => $file,
        ])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.0', 'Vote code was not found in master data.')
            ->where('result.rows.0.errors.1', 'Fund code was not found in master data.'));
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

}
