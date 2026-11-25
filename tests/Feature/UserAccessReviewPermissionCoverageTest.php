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

class UserAccessReviewPermissionCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_permissions_match_review_and_exports_and_account_status(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['name' => 'Review Permission Example']);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin);
        $url = '/user-management/access-review?search=Review%20Permission%20Example';
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1)
            ->where('users.data.0.financial_years_allowed', true)->where('users.data.0.organisational_data_allowed', true)->where('users.data.0.aip_assignments_allowed', true)->where('users.data.0.import_allowed', false));
        $response = $this->get('/user-management/access-review/export/excel?search=Review%20Permission%20Example')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'review_');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            try {
                foreach (['N', 'O', 'P'] as $column) $this->assertSame('Allowed', $book->getActiveSheet()->getCell($column.'2')->getValue());
            } finally { $book->disconnectWorksheets(); }
        } finally { unlink($path); }
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('user-access-review.pdf')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->withArgs(fn ($view, $data) => $view === 'pdf.user-access-review' && $data['rows']->count() === 1
            && $data['rows']->first()->financial_years_allowed && $data['rows']->first()->organisational_data_allowed && $data['rows']->first()->aip_assignments_allowed)->andReturn($pdf);
        $this->get('/user-management/access-review/export/pdf?search=Review%20Permission%20Example')->assertOk();
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('users.data.0.financial_years_allowed', false)
            ->where('users.data.0.organisational_data_allowed', false)->where('users.data.0.aip_assignments_allowed', false));
    }

    public function test_regular_users_cannot_access_review_or_exports(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/user-management/access-review', '/user-management/access-review/export/excel', '/user-management/access-review/export/pdf'] as $url) $this->get($url)->assertForbidden();
    }
}
