<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class VoteExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_vote_list(): void
    {
        $this->get('/votes/export/excel')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_vote_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/votes/export/excel')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_vote_data_as_excel(): void
    {
        $admin = $this->administrator();

        Vote::create([
            'code' => 'V-01',
            'description' => 'Active vote',
            'is_active' => true,
        ]);

        Vote::create([
            'code' => 'V-02',
            'description' => 'Inactive vote',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get('/votes/export/excel');

        $response->assertOk();
        $response->assertDownload('votes.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'vote_export_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame('Vote code', $sheet->getCell('A1')->getValue());
                $this->assertSame('Vote description', $sheet->getCell('B1')->getValue());
                $this->assertSame('Status', $sheet->getCell('C1')->getValue());

                $this->assertSame('V-01', $sheet->getCell('A2')->getValue());
                $this->assertSame('Active vote', $sheet->getCell('B2')->getValue());
                $this->assertSame('Active', $sheet->getCell('C2')->getValue());

                $this->assertSame('V-02', $sheet->getCell('A3')->getValue());
                $this->assertSame('Inactive vote', $sheet->getCell('B3')->getValue());
                $this->assertSame('Inactive', $sheet->getCell('C3')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }

    public function test_a_description_starting_with_equals_is_exported_as_text(): void
    {
        $admin = $this->administrator();

        Vote::create([
            'code' => 'V-01',
            'description' => '=1+1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/votes/export/excel');

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'vote_export_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $cell = $spreadsheet->getActiveSheet()->getCell('B2');

                $this->assertSame('=1+1', $cell->getValue());
                $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
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