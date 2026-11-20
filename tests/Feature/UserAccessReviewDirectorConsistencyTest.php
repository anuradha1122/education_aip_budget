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

class UserAccessReviewDirectorConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_scope_changes_are_reflected_in_review_and_exports(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['name' => 'Director Scope Check']);
        $office = DB::table('offices')->insertGetId(['code' => 'DSC', 'name' => 'Scope office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $designation = DB::table('designations')->insertGetId(['code' => 'DSC', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => 'Scope officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $officer, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin);
        $this->assertReview(true);
        DB::table('officers')->where('id', $officer)->update(['is_active' => false]);
        $this->assertReview(false);
        DB::table('officers')->where('id', $officer)->update(['is_active' => true]);
        DB::table('offices')->where('id', $office)->update(['is_active' => false]);
        $this->assertReview(false);
        $response = $this->get('/user-management/access-review/export/excel?search=Director%20Scope%20Check')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'scope_');
        $this->assertNotFalse($path);
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            try { $this->assertSame('Not allowed', $book->getActiveSheet()->getCell('L2')->getValue()); }
            finally { $book->disconnectWorksheets(); }
        } finally { unlink($path); }
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('user-access-review.pdf')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->withArgs(fn ($view, $data) => $view === 'pdf.user-access-review'
            && $data['rows']->count() === 1 && $data['rows']->first()->register_allowed === false)->andReturn($pdf);
        $this->get('/user-management/access-review/export/pdf?search=Director%20Scope%20Check')->assertOk();
    }

    private function assertReview(bool $allowed): void
    {
        $this->get('/user-management/access-review?search=Director%20Scope%20Check')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.register_allowed', $allowed)->where('users.data.0.import_allowed', false));
    }
}
