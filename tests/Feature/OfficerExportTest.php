<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OfficerExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_officer_list(): void
    {
        $this->get('/officers/export/excel')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_officer_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/officers/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_officers_as_excel(): void
    {
        $office = Office::create([
            'code' => 'OFF-01',
            'name' => 'Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $designation = Designation::create([
            'code' => 'DES-01',
            'name' => 'Test Designation',
            'is_active' => true,
        ]);

        Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => '=Test Officer',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/officers/export/excel');

        $response->assertOk();
        $response->assertDownload('officers.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'officer_export_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame('Officer name', $sheet->getCell('A1')->getValue());
                $this->assertSame('Office code', $sheet->getCell('B1')->getValue());
                $this->assertSame('Office name', $sheet->getCell('C1')->getValue());
                $this->assertSame('Designation code', $sheet->getCell('D1')->getValue());
                $this->assertSame('Designation name', $sheet->getCell('E1')->getValue());
                $this->assertSame('Status', $sheet->getCell('F1')->getValue());

                $this->assertSame('=Test Officer', $sheet->getCell('A2')->getValue());
                $this->assertSame(
                    DataType::TYPE_STRING,
                    $sheet->getCell('A2')->getDataType()
                );
                $this->assertSame('OFF-01', $sheet->getCell('B2')->getValue());
                $this->assertSame('Test Office', $sheet->getCell('C2')->getValue());
                $this->assertSame('DES-01', $sheet->getCell('D2')->getValue());
                $this->assertSame('Test Designation', $sheet->getCell('E2')->getValue());
                $this->assertSame('Inactive', $sheet->getCell('F2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }
}