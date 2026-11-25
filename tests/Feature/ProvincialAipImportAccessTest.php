<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use App\Services\UserAccessAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProvincialAipImportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_import_requires_grant_and_revocation_takes_effect(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/aip-activities/import')->assertForbidden();
        $this->get('/aip-activities/import/template')->assertForbidden();
        $this->post('/aip-activities/import/validate', [])->assertForbidden();
        $this->post('/aip-activities/import/save', [])->assertForbidden();
        $this->actingAs($admin)->patch('/user-management/'.$user->id.'/import-permission', ['can_import_aip' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'import_permission_updated']);
        $this->actingAs($user->fresh())->get('/aip-activities')->assertInertia(fn (Assert $page) => $page->where('canImportAip', true));
        $this->get('/aip-activities/import')->assertOk();
        $this->get('/aip-activities/import/template')->assertOk();
        $this->post('/aip-activities/import/validate', [])->assertSessionHasErrors('financial_year_id');
        $this->post('/aip-activities/import/save', [])->assertSessionHasErrors('financial_year_id');
        $this->get('/user-management')->assertForbidden();
        $this->actingAs($admin)->patch('/user-management/'.$user->id.'/import-permission', ['can_import_aip' => false])->assertSessionHasNoErrors();
        $this->actingAs($user->fresh())->get('/aip-activities/import')->assertForbidden();
    }

    public function test_role_change_revokes_grant_and_changing_back_does_not_restore_it(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = DB::table('offices')->insertGetId(['code' => 'Z01', 'name' => 'Zone', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(UserAccessAssignmentService::class);
        $service->assign(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null], $admin);
        $this->actingAs($admin)->patch('/user-management/'.$user->id.'/import-permission', ['can_import_aip' => true])->assertSessionHasNoErrors();
        $service->assign(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office], $admin);
        $this->assertFalse((bool) $user->fresh()->can_import_aip);
        $this->actingAs($user->fresh())->get('/aip-activities/import')->assertForbidden();
        $service->assign(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null], $admin);
        $this->actingAs($user->fresh())->get('/aip-activities/import')->assertForbidden();
        $this->assertSame(2, DB::table('user_access_assignment_audit_events')->where('user_id', $user->id)->where('action', 'import_permission_updated')->count());
    }

    public function test_a_flag_alone_does_not_grant_import_to_other_roles_or_invalid_scope(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $office = DB::table('offices')->insertGetId(['code' => 'DENY', 'name' => 'Zone', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['zonal_admin', 'subject_director', 'provincial_admin'] as $role) {
            $user = User::factory()->create();
            $user->forceFill(['can_import_aip' => true])->save();
            DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => $role, 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($user->fresh())->get('/aip-activities/import')->assertForbidden();
            $this->post('/aip-activities/import/save', [])->assertForbidden();
        }
        $inactive = User::factory()->create();
        $inactive->forceFill(['is_active' => false])->save();
        DB::table('user_access_assignments')->insert(['user_id' => $inactive->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->patch('/user-management/'.$inactive->id.'/import-permission', ['can_import_aip' => true])->assertSessionHasErrors('can_import_aip');
    }

    public function test_permitted_provincial_admin_can_validate_and_save_a_workbook(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['can_import_aip' => true])->save();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $year = DB::table('financial_years')->insertGetId(['year' => 2030, 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $office = DB::table('offices')->insertGetId(['code' => 'OFF-01', 'name' => 'Office 01', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $designation = DB::table('designations')->insertGetId(['code' => 'DES-01', 'name' => 'Director 01', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer 01', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $subject = DB::table('subjects')->insertGetId(['office_id' => $office, 'code' => 'SCI-01', 'name' => 'Science', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer, 'subject_id' => $subject]);
        DB::table('votes')->insert(['code' => 'V1', 'description' => 'Vote', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('funds')->insert(['code' => 'PF', 'name' => 'Fund', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['Serial number', 'Level', 'Activity Number', 'Activity', 'Subject code', 'Subject', 'Officer', 'Designation', 'Vote', 'Fund'], null, 'A1');
        $sheet->fromArray([1, 'Zonal', '123', 'Science seminar', 'SCI-01', 'Science', 'Officer 01', 'Director 01', 'V1', 'PF'], null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'aip-role-');
        try {
            (new Xlsx($book))->save($path);
            $book->disconnectWorksheets();
            $file = new UploadedFile($path, 'aip.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $data = ['financial_year_id' => $year, 'office_id' => $office, 'file' => $file];
            $this->actingAs($user)->post('/aip-activities/import/validate', $data)->assertOk()->assertInertia(fn (Assert $page) => $page->has('result.rows.0.errors', 0));
            $this->post('/aip-activities/import/save', $data)->assertSessionHasNoErrors();
            $this->assertDatabaseHas('aip_activities', ['financial_year_id' => $year, 'activity_number' => '123']);
        } finally { if (file_exists($path)) unlink($path); }
    }
}
