<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\User;
use App\Services\OfficerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficerUserLinkChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_officer_cannot_transfer_or_change_active_status_until_unlinked(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $first = $this->office('A');
        $second = $this->office('B');
        $designation = DB::table('designations')->insertGetId(['code' => 'DIR', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = Officer::create(['office_id' => $first, 'designation_id' => $designation, 'name' => 'Officer One', 'is_active' => true]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $first, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $officer->id, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(OfficerService::class);
        foreach ([[$second, true, 'office_id'], [$first, false, 'is_active']] as [$office, $active, $field]) {
            try {
                $service->update($officer, ['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer One', 'is_active' => $active], $admin);
                $this->fail('A linked officer must not change office or active status.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $this->assertDatabaseHas('officers', ['id' => $officer->id, 'office_id' => $first, 'is_active' => true]);
        $this->assertDatabaseCount('officer_audit_events', 0);
        $this->actingAs($admin)->delete('/user-management/'.$user->id.'/officer')->assertRedirect('/user-management');
        $service->update($officer, ['office_id' => $second, 'designation_id' => $designation, 'name' => 'Officer One', 'is_active' => true], $admin);
        $this->assertDatabaseHas('officers', ['id' => $officer->id, 'office_id' => $second]);
        $this->assertDatabaseCount('officer_audit_events', 1);
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'action' => 'officer_unlinked']);
        $this->actingAs($user)->get('/aip-activities')->assertForbidden();
    }

    public function test_linked_officer_can_change_name_without_changing_access(): void
    {
        $user = User::factory()->create();
        $office = $this->office('A');
        $designation = DB::table('designations')->insertGetId(['code' => 'DIR', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = Officer::create(['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer One', 'is_active' => true]);
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $officer->id, 'created_at' => now(), 'updated_at' => now()]);
        app(OfficerService::class)->update($officer, ['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer Renamed', 'is_active' => true]);
        $this->assertDatabaseHas('officers', ['id' => $officer->id, 'name' => 'Officer Renamed']);
        $this->assertDatabaseHas('user_officer_links', ['user_id' => $user->id, 'officer_id' => $officer->id]);
    }

    public function test_legacy_link_to_inactive_officer_blocks_reactivation(): void
    {
        $user = User::factory()->create();
        $office = $this->office('A');
        $designation = DB::table('designations')->insertGetId(['code' => 'DIR', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = Officer::create(['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer One', 'is_active' => false]);
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $officer->id, 'created_at' => now(), 'updated_at' => now()]);
        try {
            app(OfficerService::class)->update($officer, ['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer One', 'is_active' => true]);
            $this->fail('A linked inactive officer must not be reactivated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_active', $exception->errors());
        }
        $this->assertDatabaseHas('officers', ['id' => $officer->id, 'is_active' => false]);
        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    private function office(string $code): int
    {
        return DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
