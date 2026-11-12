<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExpenseCategoryImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_a_guest_cannot_open_the_import_page(): void
    {
        $this->get('/expense-categories/import')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/expense-categories/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_without_saving(): void
    {
        $file = $this->workbook([
            [' category-01 ', 'Test expense category', 'Active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/expense-categories/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expense-categories/import')
                ->where('result.total', 1)
                ->where('result.invalid', 0)
                ->where('result.rows.0.code', 'CATEGORY-01')
            );

        $this->assertDatabaseCount('expense_categories', 0);
        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }

    public function test_duplicate_and_invalid_rows_are_reported(): void
    {
        ExpenseCategory::create([
            'code' => 'EXISTING',
            'name' => 'Existing expense category',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['EXISTING', 'Existing code', 'active'],
            ['NEW-01', 'First occurrence', 'active'],
            ['new-01', 'Duplicate', 'inactive'],
            ['NEW-02', 'Invalid status', 'unknown'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/expense-categories/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expense-categories/import')
                ->where('result.total', 4)
                ->where('result.invalid', 3)
            );

        $this->assertDatabaseCount('expense_categories', 1);
        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'expense category_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Expense category code', 'Expense category name', 'Status'],
            ...$rows,
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'expense-category-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}

