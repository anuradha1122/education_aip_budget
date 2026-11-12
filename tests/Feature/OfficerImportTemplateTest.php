<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OfficerImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/officers/import/template')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/officers/import/template')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_blank_template(): void
    {
        $response = $this->actingAs($this->administrator())
            ->get('/officers/import/template');

        $response->assertOk();
        $response->assertDownload('officer-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'officer_template_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame(
                    'Officer name',
                    $sheet->getCell('A1')->getValue()
                );
                $this->assertSame(
                    'Office code',
                    $sheet->getCell('B1')->getValue()
                );
                $this->assertSame(
                    'Designation code',
                    $sheet->getCell('C1')->getValue()
                );
                $this->assertSame(
                    'Status',
                    $sheet->getCell('D1')->getValue()
                );

                $this->assertNull($sheet->getCell('A2')->getValue());
                $this->assertNull($sheet->getCell('B2')->getValue());
                $this->assertNull($sheet->getCell('C2')->getValue());
                $this->assertNull($sheet->getCell('D2')->getValue());
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