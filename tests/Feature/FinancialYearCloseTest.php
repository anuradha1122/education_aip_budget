<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialYearCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_close_year_with_audit_and_cannot_create_aip_activity(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $year = FinancialYear::create(['year' => 2030, 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'is_closed' => false]);
        $url = '/financial-years/'.$year->id.'/close';
        $this->actingAs($admin)->patch($url)->assertRedirect(route('financial-years.index'))->assertSessionHasNoErrors();
        $this->assertTrue($year->fresh()->is_closed);
        $event = DB::table('financial_year_audit_events')->where('financial_year_id', $year->id)->where('action', 'closed')->first();
        $this->assertNotNull($event);
        $this->assertEquals($admin->id, $event->actor_id);
        $this->assertFalse(json_decode($event->old_values, true)['is_closed']);
        $this->assertTrue(json_decode($event->new_values, true)['is_closed']);
        $this->patch($url)->assertRedirect(route('financial-years.index'));
        $this->assertDatabaseCount('financial_year_audit_events', 1);
        $this->post('/aip-activities', ['financial_year_id' => $year->id, 'activity_number' => '55', 'description' => 'Blocked'])->assertSessionHasErrors('financial_year_id');
        $this->assertDatabaseMissing('aip_activities', ['activity_number' => '55']);
    }

    public function test_guest_and_regular_user_cannot_close_year(): void
    {
        $year = FinancialYear::create(['year' => 2030, 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'is_closed' => false]);
        $url = '/financial-years/'.$year->id.'/close';
        $this->patch($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch($url)->assertForbidden();
        $this->assertFalse($year->fresh()->is_closed);
        $this->assertDatabaseCount('financial_year_audit_events', 0);
    }
}
