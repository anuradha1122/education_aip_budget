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

class AipImportActivityNumberFormatTest extends TestCase
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

    public function test_zero_formatted_numeric_identifier_is_reviewed_and_saved_with_zeros(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')], [
            'C2' => ['value' => 1, 'type' => DataType::TYPE_NUMERIC, 'format' => '000'],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.activity_number', '001')->has('result.rows.0.errors', 0));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('aip_activities', ['financial_year_id' => $year, 'activity_number' => '001']);
        $this->assertDatabaseMissing('aip_activities', ['financial_year_id' => $year, 'activity_number' => '1']);
    }

    public function test_text_zero_and_numeric_general_are_distinct(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01'), $this->row('01')], [
            'C2' => ['value' => '001', 'type' => DataType::TYPE_STRING],
            'C3' => ['value' => 1, 'type' => DataType::TYPE_NUMERIC, 'format' => 'General'],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.activity_number', '001')->has('result.rows.0.errors', 0)
            ->where('result.rows.1.activity_number', '1')->has('result.rows.1.errors', 0));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('aip_activities', ['financial_year_id' => $year, 'activity_number' => '001']);
        $this->assertDatabaseHas('aip_activities', ['financial_year_id' => $year, 'activity_number' => '1']);
    }

    public function test_matching_formatted_and_text_identifiers_are_duplicates(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01'), $this->row('01')], [
            'C2' => ['value' => 1, 'type' => DataType::TYPE_NUMERIC, 'format' => '000'],
            'C3' => ['value' => '001', 'type' => DataType::TYPE_STRING],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.0', 'Activity number is repeated in this workbook.')
            ->where('result.rows.1.errors.0', 'Activity number is repeated in this workbook.'));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_unsupported_numeric_format_is_rejected_without_writes(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01')], [
            'C2' => ['value' => 1234, 'type' => DataType::TYPE_NUMERIC, 'format' => '#,##0'],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.0', 'Activity number has an unsupported numeric value or format. Enter it as text.'));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_downloaded_template_formats_activity_numbers_as_text(): void
    {
        $response = $this->actingAs($this->admin())->get('/aip-activities/import/template');
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'aip-format-');
        try {
            file_put_contents($path, $response->streamedContent());
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame('@', $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
            $styleIndex = $sheet->getColumnDimension('C')->getXfIndex();
            $this->assertNotNull($styleIndex);
            $this->assertSame('@', $spreadsheet->getCellXfByIndex($styleIndex)->getNumberFormat()->getFormatCode());
            $spreadsheet->disconnectWorksheets();
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_large_numeric_identifier_is_rejected_but_text_identifier_is_accepted(): void
    {
        $year = $this->year();
        [$office] = $this->office('01');
        $this->masterFunding();
        $this->actingAs($this->admin());
        $file = $this->workbook([$this->row('01'), $this->row('01')], [
            'C2' => ['value' => 1234567890123456, 'type' => DataType::TYPE_NUMERIC, 'format' => 'General'],
            'C3' => ['value' => '1234567890123456', 'type' => DataType::TYPE_STRING],
        ]);
        $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
        $this->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.rows.0.errors.0', 'Activity number has an unsupported numeric value or format. Enter it as text.')
            ->where('result.rows.1.activity_number', '1234567890123456')
            ->has('result.rows.1.errors', 0));
        $this->post('/aip-activities/import/save', $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('aip_activities', 0);
    }
}
