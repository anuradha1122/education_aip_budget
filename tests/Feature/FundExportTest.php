<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FundExportTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_a_guest_cannot_download_the_fund_list(): void
    {
        $this->get('/funds/export/excel')
            ->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_download_the_fund_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/funds/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_fund_data_as_excel(): void
    {
        Fund::create([
            'code' => 'PF',
            'name' => 'Provincial Fund',
            'is_active' => true,
        ]);

        Fund::create([
            'code' => 'TEST',
            'name' => 'Test Fund',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/funds/export/excel');

        $response->assertOk();
        $response->assertDownload('fund-list.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'fund-export-');

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame('Fund code', $sheet->getCell('A1')->getValue());
                $this->assertSame('Fund name', $sheet->getCell('B1')->getValue());
                $this->assertSame('Status', $sheet->getCell('C1')->getValue());

                $this->assertSame('PF', $sheet->getCell('A2')->getValue());
                $this->assertSame('Provincial Fund', $sheet->getCell('B2')->getValue());
                $this->assertSame('Active', $sheet->getCell('C2')->getValue());

                $this->assertSame('TEST', $sheet->getCell('A3')->getValue());
                $this->assertSame('Inactive', $sheet->getCell('C3')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_values_starting_with_equals_are_exported_as_text(): void
    {
        Fund::create([
            'code' => 'SAFE-01',
            'name' => '=1+1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/funds/export/excel');

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'fund-export-');

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::load($path);

            try {
                $cell = $spreadsheet->getActiveSheet()->getCell('B2');

                $this->assertSame('=1+1', $cell->getValue());
                $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            @unlink($path);
        }
    }
}
