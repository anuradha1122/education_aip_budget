<?php

namespace Tests\Feature;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class UserAccessReviewOfficeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_search_is_shared_by_review_and_exports(): void
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->forceFill(['is_admin' => true])->save();
        $office = DB::table('offices')->insertGetId(['code' => 'ZEO-RAT', 'name' => 'Zonal Education Office Ratnapura', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('offices')->insertGetId(['code' => 'ZEO-KEG', 'name' => 'Kegalle', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['Alice', $office], ['Bob', $office], ['Other', $other]] as [$name, $id]) {
            $user = User::factory()->create(['name' => $name]);
            DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($admin)->get('/user-management/access-review?search=Ratnapura')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 2)->where('users.data.0.name', 'Alice')->where('users.data.1.name', 'Bob'));
        $this->get('/user-management/access-review?search=ZEO-RAT')->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 2));
        $response = $this->get('/user-management/access-review/export/excel?search=ZEO-RAT')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'access_');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            try {
                $sheet = $book->getActiveSheet();
                $this->assertSame('Alice', $sheet->getCell('A2')->getValue());
                $this->assertSame('Bob', $sheet->getCell('A3')->getValue());
                $this->assertNull($sheet->getCell('A4')->getValue());
            } finally { $book->disconnectWorksheets(); }
        } finally { unlink($path); }
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('user-access-review.pdf')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            return $view === 'pdf.user-access-review' && $data['search'] === 'Ratnapura'
                && $data['rows']->pluck('name')->all() === ['Alice', 'Bob'];
        })->andReturn($pdf);
        $this->get('/user-management/access-review/export/pdf?search=Ratnapura')->assertOk();
    }
}
