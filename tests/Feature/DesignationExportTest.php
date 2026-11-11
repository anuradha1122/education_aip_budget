<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DesignationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_designation_list(): void
    {
        $this->get('/designations/export/excel')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_designation_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/designations/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_designations_as_excel(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        Designation::create([
            'code' => 'ADE',
            'name' => 'Assistant Director',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get('/designations/export/excel');

        $response->assertOk();
        $response->assertDownload('designations.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'designation_export_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame(
                    'Designation code',
                    $sheet->getCell('A1')->getValue()
                );

                $this->assertSame(
                    'Designation name',
                    $sheet->getCell('B1')->getValue()
                );

                $this->assertSame(
                    'Status',
                    $sheet->getCell('C1')->getValue()
                );

                $this->assertSame('ADE', $sheet->getCell('A2')->getValue());
                $this->assertSame(
                    'Assistant Director',
                    $sheet->getCell('B2')->getValue()
                );
                $this->assertSame('Inactive', $sheet->getCell('C2')->getValue());

                $this->assertSame('DEO', $sheet->getCell('A3')->getValue());
                $this->assertSame(
                    'Director of Education',
                    $sheet->getCell('B3')->getValue()
                );
                $this->assertSame('Active', $sheet->getCell('C3')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }
}