<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class UserAccessReviewExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_administrator(): void
    {
        $this->get('/user-management/access-review/export/excel')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/user-management/access-review/export/excel')->assertForbidden();
    }

    public function test_export_contains_filtered_user_and_current_permissions_as_text(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['name' => '=Review Test', 'email' => 'review@example.test']);
        $office = DB::table('offices')->insertGetId(['code' => 'R1', 'name' => 'Review office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $response = $this->actingAs($admin)->get('/user-management/access-review/export/excel?search=review%40example.test');
        $response->assertOk()->assertDownload('user-access-review.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'access_review_');
        $this->assertNotFalse($path);
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            try {
                $sheet = $book->getActiveSheet();
                $this->assertSame('=Review Test', $sheet->getCell('A2')->getValue());
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
                $this->assertSame('review@example.test', $sheet->getCell('B2')->getValue());
                $this->assertSame('zonal_admin', $sheet->getCell('D2')->getValue());
                $this->assertSame('Review office', $sheet->getCell('F2')->getValue());
                $this->assertSame('Allowed', $sheet->getCell('L2')->getValue());
                $this->assertSame('Not allowed', $sheet->getCell('M2')->getValue());
                $this->assertNull($sheet->getCell('A3')->getValue());
            } finally {
                $book->disconnectWorksheets();
            }
        } finally {
            unlink($path);
        }
    }
}
