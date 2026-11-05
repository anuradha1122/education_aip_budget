<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FundImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/funds/import/template')
            ->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_download_the_template(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/funds/import/template')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_blank_template(): void
    {
        $response = $this->actingAs($this->administrator())
            ->get('/funds/import/template');

        $response->assertOk();
        $response->assertDownload('fund-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'fund-template-');

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame('Funds', $sheet->getTitle());
                $this->assertSame('Fund code', $sheet->getCell('A1')->getValue());
                $this->assertSame('Fund name', $sheet->getCell('B1')->getValue());
                $this->assertSame('Status', $sheet->getCell('C1')->getValue());

                $this->assertNull($sheet->getCell('A2')->getValue());
                $this->assertNull($sheet->getCell('B2')->getValue());
                $this->assertNull($sheet->getCell('C2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            @unlink($path);
        }
    }
}
