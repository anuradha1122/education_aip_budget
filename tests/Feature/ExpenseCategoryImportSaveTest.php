<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExpenseCategoryImportSaveTest extends TestCase
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

    public function test_a_guest_cannot_import_expense_categories(): void
    {
        $this->post('/expense-categories/import')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('expense_categories', 0);
    }

    public function test_a_regular_user_cannot_import_expense_categories(): void
    {
        $user = User::factory()->create();

        $file = $this->workbook([
            ['NEW-01', 'New expense category', 'active'],
        ]);

        $this->actingAs($user)
            ->post('/expense-categories/import', ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('expense_categories', 0);
        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }

    public function test_an_administrator_can_import_reviewed_expense_categories_with_audit_events(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['category-01', 'First expense category', 'active'],
            ['category-02', 'Second expense category', 'inactive'],
        ]);

        $this->actingAs($admin)
            ->post('/expense-categories/import/validate', ['file' => $file])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/expense-categories/import', ['file' => $file])
            ->assertRedirect(route('expense-categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expense_categories', [
            'code' => 'CATEGORY-01',
            'name' => 'First expense category',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('expense_categories', [
            'code' => 'CATEGORY-02',
            'name' => 'Second expense category',
            'is_active' => false,
        ]);

        $firstExpenseCategory = ExpenseCategory::query()->where('code', 'CATEGORY-01')->firstOrFail();
        $secondExpenseCategory = ExpenseCategory::query()->where('code', 'CATEGORY-02')->firstOrFail();

        $this->assertDatabaseCount('expense_categories', 2);
        $this->assertDatabaseCount('expense_category_audit_events', 2);

        $this->assertDatabaseHas('expense_category_audit_events', [
            'expense_category_id' => $firstExpenseCategory->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas('expense_category_audit_events', [
            'expense_category_id' => $secondExpenseCategory->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_unreviewed_file_cannot_be_imported(): void
    {
        $file = $this->workbook([
            ['NEW-01', 'New expense category', 'active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/expense-categories/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('expense_categories', 0);
        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }

    public function test_a_different_file_cannot_be_imported_after_review(): void
    {
        $admin = $this->administrator();

        $reviewedFile = $this->workbook([
            ['NEW-01', 'First expense category', 'active'],
        ]);

        $differentFile = $this->workbook([
            ['NEW-02', 'Second expense category', 'active'],
        ]);

        $this->actingAs($admin)
            ->post('/expense-categories/import/validate', ['file' => $reviewedFile])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/expense-categories/import', ['file' => $differentFile])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('expense_categories', 0);
        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }

    public function test_an_invalid_workbook_cannot_be_imported(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'Valid expense category', 'active'],
            ['NEW-02', 'Invalid expense category', 'unknown'],
        ]);

        $this->actingAs($admin)
            ->post('/expense-categories/import/validate', ['file' => $file])
            ->assertOk();

        $this->actingAs($admin)
            ->post('/expense-categories/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('expense_categories', 0);
        $this->assertDatabaseCount('expense_category_audit_events', 0);
    }

    public function test_no_expense_categories_are_imported_if_a_code_is_added_after_review(): void
    {
        $admin = $this->administrator();

        $file = $this->workbook([
            ['NEW-01', 'First expense category', 'active'],
            ['NEW-02', 'Second expense category', 'active'],
        ]);

        $this->actingAs($admin)
            ->post('/expense-categories/import/validate', ['file' => $file])
            ->assertOk();

        ExpenseCategory::create([
            'code' => 'NEW-02',
            'name' => 'Existing expense category',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/expense-categories/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('expense_categories', 1);

        $this->assertDatabaseMissing('expense_categories', [
            'code' => 'NEW-01',
        ]);

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
        $path = tempnam(sys_get_temp_dir(), 'expense_category_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->fromArray([
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