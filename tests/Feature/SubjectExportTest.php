<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SubjectExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_subject_list(): void
    {
        $this->get('/subjects/export/excel')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_subject_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/subjects/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_subject_data_as_excel(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        $office = Office::create([
            'code' => 'OFF-01',
            'name' => 'Test office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        Subject::create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => '=Test subject',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get('/subjects/export/excel');

        $response->assertOk();
        $response->assertDownload('subjects.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'subject_export_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame('Office code', $sheet->getCell('A1')->getValue());
                $this->assertSame('Subject name', $sheet->getCell('D1')->getValue());
                $this->assertSame('OFF-01', $sheet->getCell('A2')->getValue());
                $this->assertSame('Test office', $sheet->getCell('B2')->getValue());
                $this->assertSame('SUB-01', $sheet->getCell('C2')->getValue());
                $this->assertSame('=Test subject', $sheet->getCell('D2')->getValue());
                $this->assertSame(
                    DataType::TYPE_STRING,
                    $sheet->getCell('D2')->getDataType()
                );
                $this->assertSame('Inactive', $sheet->getCell('E2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }
}