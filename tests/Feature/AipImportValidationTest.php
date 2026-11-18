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
        $user->forceFill(['is_admin' => true])->save();

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
                ->has('result.rows.0.errors', 1)
                ->has('result.rows.1.errors', 1)
                ->has('result.rows.2.errors', 1));

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
            ->has('result.rows.2.errors', 0));

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
            ->has('result.rows.0.errors', 0)
            ->where('result.rows.1.errors.0', 'Subject code was not found for the selected office.')
            ->where('result.rows.2.errors.0', 'Subject code was not found for the selected office.')
            ->has('result.rows.3.errors', 0));
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
