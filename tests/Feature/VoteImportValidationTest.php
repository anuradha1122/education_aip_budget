<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VoteImportValidationTest extends TestCase
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
        $this->get('/votes/import')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_validate_an_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/votes/import/validate')
            ->assertForbidden();
    }

    public function test_an_administrator_can_validate_without_saving(): void
    {
        $file = $this->workbook([
            [' vote-01 ', 'Test vote', 'Active'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/votes/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('votes/import')
                ->where('result.total', 1)
                ->where('result.invalid', 0)
                ->where('result.rows.0.code', 'VOTE-01')
            );

        $this->assertDatabaseCount('votes', 0);
        $this->assertDatabaseCount('vote_audit_events', 0);
    }

    public function test_duplicate_and_invalid_rows_are_reported(): void
    {
        Vote::create([
            'code' => 'EXISTING',
            'description' => 'Existing vote',
            'is_active' => true,
        ]);

        $file = $this->workbook([
            ['EXISTING', 'Existing code', 'active'],
            ['NEW-01', 'First occurrence', 'active'],
            ['new-01', 'Duplicate', 'inactive'],
            ['NEW-02', 'Invalid status', 'unknown'],
        ]);

        $this->actingAs($this->administrator())
            ->post('/votes/import/validate', ['file' => $file])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('votes/import')
                ->where('result.total', 4)
                ->where('result.invalid', 3)
            );

        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseCount('vote_audit_events', 0);
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
        $path = tempnam(sys_get_temp_dir(), 'vote_import_');
        $this->assertNotFalse($path);

        $this->temporaryFiles[] = $path;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Vote code', 'Vote description', 'Status'],
            ...$rows,
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'vote-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}