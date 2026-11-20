<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AipImportCodeFormatTest extends TestCase
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

    private function workbook(array $rows, array $cells = []): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Serial number', 'Level', 'Activity Number', 'Activity', 'Subject code', 'Subject', 'Officer', 'Designation', 'Vote', 'Fund'], null, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }
        foreach ($cells as $address => $cell) {
            $sheet->setCellValueExplicit($address, $cell['value'], $cell['type']);
            if (isset($cell['format'])) {
                $sheet->getStyle($address)->getNumberFormat()->setFormatCode($cell['format']);
            }
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

    public function test_numeric_subject_and_vote_codes_preserve_zeros_and_text_fund_is_saved(): void
    {
        $year = $this->year();
        [$office, $officer, $subject] = $this->office('01');
        $this->masterFunding();
        DB::table('subjects')->where('id', $subject)->update(['code' => '001']);
        DB::table('votes')->where('code', 'V1')->update(['code' => '002']);
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')], [
            'E2' => ['value' => 1, 'type' => DataType::TYPE_NUMERIC, 'format' => '000'],
            'I2' => ['value' => 2, 'type' => DataType::TYPE_NUMERIC, 'format' => '000'],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.subject_code', '001')
            ->where('result.rows.0.vote', '002')
            ->where('result.rows.0.fund', 'PF')
            ->has('result.rows.0.errors', 0));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasNoErrors();
        $activity = DB::table('aip_activities')->where('financial_year_id', $year)->where('activity_number', '123')->first();
        $this->assertNotNull($activity);
        $this->assertDatabaseHas('aip_activity_offices', ['aip_activity_id' => $activity->id, 'subject_id' => $subject, 'officer_id' => $officer]);
        $this->assertDatabaseHas('aip_activity_funding_entries', ['aip_activity_id' => $activity->id, 'vote_id' => DB::table('votes')->where('code', '002')->value('id'), 'fund_id' => DB::table('funds')->where('code', 'PF')->value('id')]);
    }

    public function test_unsupported_numeric_subject_and_vote_codes_are_rejected(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')], [
            'E2' => ['value' => 1234, 'type' => DataType::TYPE_NUMERIC, 'format' => '#,##0'],
            'I2' => ['value' => 1.5, 'type' => DataType::TYPE_NUMERIC, 'format' => 'General'],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.0', 'Vote code has an unsupported numeric value or format. Enter it as text.')
            ->where('result.rows.0.errors.1', 'Subject code has an unsupported numeric value or format. Enter it as text.'));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_template_sets_text_column_defaults_for_identifiers(): void
    {
        $response = $this->actingAs($this->admin())->get('/aip-activities/import/template');
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'aip-template-');
        try {
            $content = $response->streamedContent();
            file_put_contents($path, $content);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            foreach (['C', 'E', 'I', 'J'] as $column) {
                $styleIndex = $sheet->getColumnDimension($column)->getXfIndex();
                $this->assertNotNull($styleIndex);
                $this->assertSame('@', $spreadsheet->getCellXfByIndex($styleIndex)->getNumberFormat()->getFormatCode());
            }
            $spreadsheet->disconnectWorksheets();
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
