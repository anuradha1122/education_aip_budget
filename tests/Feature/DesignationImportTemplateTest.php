<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DesignationImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/designations/import/template')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/designations/import/template')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_blank_template(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        $response = $this->actingAs($admin)
            ->get('/designations/import/template');

        $response->assertOk();
        $response->assertDownload('designation-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'designation_template_');
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

                $this->assertNull($sheet->getCell('A2')->getValue());
                $this->assertNull($sheet->getCell('B2')->getValue());
                $this->assertNull($sheet->getCell('C2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }
}