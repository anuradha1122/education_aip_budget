<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UniqueOfficerUserLinkTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(); $admin->forceFill(['is_admin' => true])->save();
        $one = User::factory()->create(); $two = User::factory()->create();
        $office = DB::table('offices')->insertGetId(['code' => 'U01', 'name' => 'Office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $designation = DB::table('designations')->insertGetId(['code' => 'U01', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => 'First officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => 'Second officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$one, $two] as $user) DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        return [$admin, $one, $two, $office, $officer, $other];
    }

    public function test_an_officer_cannot_be_linked_to_two_accounts_and_unlinking_allows_reuse(): void
    {
        [$admin, $one, $two, , $officer, $other] = $this->fixture();
        $this->actingAs($admin)->put('/user-management/'.$one->id.'/officer', ['officer_id' => $officer])->assertSessionHasNoErrors();
        $this->put('/user-management/'.$two->id.'/officer', ['officer_id' => $other])->assertSessionHasNoErrors();
        $this->get('/user-management')->assertInertia(fn (Assert $page) => $page->has('officers', 2)->where('officers.0.is_linked', fn ($value) => (bool) $value)->where('officers.1.is_linked', fn ($value) => (bool) $value));
        $this->put('/user-management/'.$two->id.'/officer', ['officer_id' => $officer])->assertSessionHasErrors('officer_id');
        $this->assertDatabaseHas('user_officer_links', ['user_id' => $two->id, 'officer_id' => $other]);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 2);
        $this->delete('/user-management/'.$one->id.'/officer')->assertSessionHasNoErrors();
        $this->put('/user-management/'.$two->id.'/officer', ['officer_id' => $officer])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_officer_links', ['user_id' => $two->id, 'officer_id' => $officer]);
    }

    public function test_officer_can_still_be_assigned_multiple_subjects(): void
    {
        [$admin, $one, , $office, $officer] = $this->fixture();
        foreach (['SCI', 'MATH'] as $code) {
            $subject = DB::table('subjects')->insertGetId(['office_id' => $office, 'code' => $code, 'name' => 'Subject '.$code, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer, 'subject_id' => $subject], $admin);
        }
        $this->assertSame(2, DB::table('officer_subject_assignments')->where('officer_id', $officer)->whereNull('ended_at')->count());
        $this->actingAs($admin)->put('/user-management/'.$one->id.'/officer', ['officer_id' => $officer])->assertSessionHasNoErrors();
    }

    public function test_database_rejects_duplicate_officer_links(): void
    {
        [, $one, $two, , $officer] = $this->fixture();
        DB::table('user_officer_links')->insert(['user_id' => $one->id, 'officer_id' => $officer, 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('user_officer_links')->insert(['user_id' => $two->id, 'officer_id' => $officer, 'created_at' => now(), 'updated_at' => now()]);
    }
}
