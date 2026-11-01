<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OfficeImportTemplateTest extends TestCase
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

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/offices/import/template')
            ->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_download_the_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/offices/import/template')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_template(): void
    {
        $response = $this->actingAs($this->administrator())
            ->get('/offices/import/template');

        $response->assertOk();

        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response->assertDownload('office-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'office-template-');

        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame(
                    'Office code',
                    $sheet->getCell('A1')->getValue()
                );

                $this->assertSame(
                    'Office name',
                    $sheet->getCell('B1')->getValue()
                );

                $this->assertSame(
                    'Office type',
                    $sheet->getCell('C1')->getValue()
                );

                $this->assertSame(
                    'Status',
                    $sheet->getCell('D1')->getValue()
                );

                $this->assertNull($sheet->getCell('A2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }
}
