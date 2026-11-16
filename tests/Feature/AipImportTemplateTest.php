<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AipImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/aip-activities/import/template')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_template(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/aip-activities/import/template')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_blank_template(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $response = $this->actingAs($admin)
            ->get('/aip-activities/import/template');

        $response->assertOk();
        $response->assertDownload('aip-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'aip-template-');

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('AIP', $sheet->getTitle());

            $this->assertSame([
                'Serial number',
                'Level',
                'Activity Number',
                'Activity',
                'Subject code',
                'Subject',
                'Officer',
                'Designation',
                'Vote',
                'Fund',
            ], $sheet->rangeToArray('A1:J1')[0]);

            $this->assertNull($sheet->getCell('A2')->getValue());

            $spreadsheet->disconnectWorksheets();
        } finally {
            if ($path !== false && file_exists($path)) {
                unlink($path);
            }
        }
    }
}