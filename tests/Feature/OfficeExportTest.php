<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OfficeExportTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    public function test_a_guest_cannot_download_the_office_list(): void
    {
        $this->get('/offices/export/excel')
            ->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_download_the_office_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/offices/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_office_data_as_excel(): void
    {
        Office::create([
            'code' => 'ZONE-01',
            'name' => '=Example Office',
            'type' => 'zonal',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/offices/export/excel');

        $response->assertOk();

        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response->assertHeader(
            'content-disposition',
            'attachment; filename=offices.xlsx'
        );

        $path = tempnam(sys_get_temp_dir(), 'office-export-');

        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame('Office code', $sheet->getCell('A1')->getValue());
                $this->assertSame('ZONE-01', $sheet->getCell('A2')->getValue());
                $this->assertSame('=Example Office', $sheet->getCell('B2')->getValue());
                $this->assertSame(
                    DataType::TYPE_STRING,
                    $sheet->getCell('B2')->getDataType()
                );
                $this->assertSame('zonal', $sheet->getCell('C2')->getValue());
                $this->assertSame('Inactive', $sheet->getCell('D2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }
}
