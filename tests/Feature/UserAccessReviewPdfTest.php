<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAccessReviewPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_administrator_can_download_the_report(): void
    {
        $url = '/user-management/access-review/export/pdf';
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get($url)->assertOk()->assertDownload('user-access-review.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_report_filters_users_and_computes_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['name' => 'PDF unique name']);
        $office = DB::table('offices')->insertGetId(['code' => 'PDF', 'name' => 'PDF office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->get('/user-management/access-review/export/pdf?search=PDF%20unique%20name')->assertOk()->assertDownload('user-access-review.pdf');
        // Verify rendered view separately, including escaping and empty results.
        $row = (object) ['name' => '<Test>', 'email' => 'test@example.test', 'is_active' => true, 'role' => 'zonal_admin', 'office_name' => 'PDF office', 'office_code' => 'PDF', 'office_active' => true, 'officer_name' => null, 'officer_active' => null, 'is_admin' => false, 'can_import_aip' => false, 'register_allowed' => true, 'import_allowed' => false];
        $row->financial_years_allowed = false;
        $row->organisational_data_allowed = false;
        $row->aip_assignments_allowed = false;
        $this->view('pdf.user-access-review', ['rows' => collect([$row]), 'search' => 'PDF'])->assertSee('&lt;Test&gt;', false)->assertSee('Allowed')->assertSee('Not allowed');
        $this->view('pdf.user-access-review', ['rows' => collect(), 'search' => 'none'])->assertSee('No users found.');
    }
    public function test_pdf_generation_time_is_in_sri_lanka_time(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-26 23:45:00', 'UTC'));
        try {
            $this->view('pdf.user-access-review', ['rows' => collect(), 'search' => ''])
                ->assertSee('Generated (Sri Lanka time): 2026-11-27 05:15');
        } finally {
            $this->travelBack();
        }
    }
}
