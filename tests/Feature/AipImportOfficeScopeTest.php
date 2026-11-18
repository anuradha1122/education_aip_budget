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

class AipImportOfficeScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createYear(int $year): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => $year,
            'start_date' => $year.'-01-01',
            'end_date' => $year.'-12-31',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOffice(string $code): int
    {
        return DB::table('offices')->insertGetId([
            'code' => $code,
            'name' => 'Office '.$code,
            'type' => 'zonal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createActivity(
        int $yearId,
        string $number,
        string $description
    ): int {
        return DB::table('aip_activities')->insertGetId([
            'financial_year_id' => $yearId,
            'activity_number' => $number,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function linkOffice(int $activityId, int $officeId): void
    {
        DB::table('aip_activity_offices')->insert([
            'aip_activity_id' => $activityId,
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            'Serial number',
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

        $path = tempnam(sys_get_temp_dir(), 'aip-scope-');

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

    public function test_review_checks_the_selected_office_and_financial_year(): void
    {
        $selectedYear = $this->createYear(2027);
        $previousYear = $this->createYear(2026);

        $selectedOffice = $this->createOffice('ZONE-01');
        $otherOffice = $this->createOffice('ZONE-02');

        // Activity 123 exists for 2027, but is linked to another office.
        $otherOfficeActivity = $this->createActivity(
            $selectedYear,
            '123',
            'Science seminar'
        );
        $this->linkOffice($otherOfficeActivity, $otherOffice);

        // Activity 124 exists only in the previous year.
        $this->createActivity(
            $previousYear,
            '124',
            'Mathematics workshop'
        );

        // Activity 125 is already linked to the selected office.
        $selectedOfficeActivity = $this->createActivity(
            $selectedYear,
            '125',
            'Teacher meeting'
        );
        $this->linkOffice($selectedOfficeActivity, $selectedOffice);

        $file = $this->workbook([
            [1, 'Zonal', '123', 'Science seminar'],
            [2, 'Zonal', '124', 'Mathematics workshop'],
            [3, 'Zonal', '125', 'Teacher meeting'],
        ]);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->post('/aip-activities/import/validate', [
                'financial_year_id' => $selectedYear,
                'office_id' => $selectedOffice,
                'file' => $file,
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Existing activity in another office: no selected-office
                // link warning, but it is still flagged for review.
                ->has('result.rows.0.errors', 1)
                ->where(
                    'result.rows.0.errors.0',
                    'Activity already exists for this financial year; review its existing details before importing.'
                )

                // The same number in another year is not a duplicate.
                ->has('result.rows.1.errors', 0)

                // Existing activity already linked to this office.
                ->has('result.rows.2.errors', 2)
                ->where(
                    'result.rows.2.errors.1',
                    'This activity is already linked to the selected office.'
                ));

        // Validation must not save or change records.
        $this->assertDatabaseCount('aip_activities', 3);
        $this->assertDatabaseCount('aip_activity_offices', 2);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }
}