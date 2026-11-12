<?php

namespace Tests\Feature;

use App\Models\ProgrammeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProgrammeTypeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_programme_type_list(): void
    {
        $this->get('/programme-types/export/excel')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_programme_type_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/programme-types/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_programme_types_as_excel(): void
    {
        ProgrammeType::create([
            'code' => 'TYPE-01',
            'name' => 'First type',
            'is_active' => true,
        ]);

        ProgrammeType::create([
            'code' => 'TYPE-02',
            'name' => '=Second type',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/programme-types/export/excel');

        $response->assertOk();
        $response->assertDownload('programme-types.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'programme_type_export_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame(
                    'Programme type code',
                    $sheet->getCell('A1')->getValue()
                );
                $this->assertSame(
                    'Programme type name',
                    $sheet->getCell('B1')->getValue()
                );
                $this->assertSame('Status', $sheet->getCell('C1')->getValue());

                $this->assertSame('TYPE-01', $sheet->getCell('A2')->getValue());
                $this->assertSame('First type', $sheet->getCell('B2')->getValue());
                $this->assertSame('Active', $sheet->getCell('C2')->getValue());

                $this->assertSame('TYPE-02', $sheet->getCell('A3')->getValue());
                $this->assertSame('=Second type', $sheet->getCell('B3')->getValue());
                $this->assertSame(
                    DataType::TYPE_STRING,
                    $sheet->getCell('B3')->getDataType()
                );
                $this->assertSame('Inactive', $sheet->getCell('C3')->getValue());
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