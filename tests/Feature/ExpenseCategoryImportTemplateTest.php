<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExpenseCategoryImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_template(): void
    {
        $this->get('/expense-categories/import/template')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/expense-categories/import/template')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_blank_template(): void
    {
        $response = $this->actingAs($this->administrator())
            ->get('/expense-categories/import/template');

        $response->assertOk();
        $response->assertDownload('expense-category-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'expense_category_template_');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $response->streamedContent());

            $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

            try {
                $sheet = $spreadsheet->getActiveSheet();

                $this->assertSame(
                    'Expense category code',
                    $sheet->getCell('A1')->getValue()
                );

                $this->assertSame(
                    'Expense category name',
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

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }
}