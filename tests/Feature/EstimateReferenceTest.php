<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EstimateReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function link(int $year, string $officeCode): array
    {
        $yearId = DB::table('financial_years')->where('year', $year)->value('id');
        if ($yearId === null) $yearId = DB::table('financial_years')->insertGetId(['year' => $year, 'start_date' => "$year-01-01", 'end_date' => "$year-12-31", 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $office = DB::table('offices')->where('code', $officeCode)->value('id');
        if ($office === null) $office = DB::table('offices')->insertGetId(['code' => $officeCode, 'name' => $officeCode, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $activity = DB::table('aip_activities')->insertGetId(['financial_year_id' => $yearId, 'activity_number' => (string) $office, 'description' => 'Activity', 'created_at' => now(), 'updated_at' => now()]);
        $link = DB::table('aip_activity_offices')->insertGetId(['aip_activity_id' => $activity, 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        return [(int) $link, (int) $yearId];
    }

    private function input(int $link): array
    {
        return ['aip_activity_office_id' => $link, 'title' => 'Estimate', 'items' => [['description' => 'Rate', 'rate_in_cents' => 125, 'units' => 2, 'people' => 1]]];
    }

    public function test_references_are_sequential_per_office_and_year_and_shown_on_record(): void
    {
        [$one] = $this->link(2027, 'ZEO-RAT');
        [$two] = $this->link(2027, 'ZEO-DEHI');
        [$nextYear] = $this->link(2028, 'ZEO-RAT');
        $service = app(EstimateService::class);
        $first = $service->create($this->input($one));
        $this->assertSame('2027/ZEO-RAT/EST/0001', $first['reference']);
        $this->assertSame('2027/ZEO-RAT/EST/0002', $service->create($this->input($one))['reference']);
        $this->assertSame('2027/ZEO-DEHI/EST/0001', $service->create($this->input($two))['reference']);
        $this->assertSame('2028/ZEO-RAT/EST/0001', $service->create($this->input($nextYear))['reference']);
        $admin = User::factory()->create(); $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/estimates/'.$first['id'])->assertOk()->assertInertia(fn ($page) => $page->where('estimate.reference', $first['reference']));
    }

    public function test_closed_year_or_invalid_link_does_not_issue_reference(): void
    {
        [$link, $year] = $this->link(2027, 'ZEO-RAT');
        DB::table('financial_years')->where('id', $year)->update(['is_closed' => true]);
        try {
            app(EstimateService::class)->create($this->input($link));
            $this->fail('Closed year should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('aip_activity_office_id', $e->errors());
        }
        $this->assertDatabaseCount('estimates', 0);
        $this->assertDatabaseCount('estimate_reference_sequences', 0);
        DB::table('financial_years')->where('id', $year)->update(['is_closed' => false]);
        $this->assertSame('2027/ZEO-RAT/EST/0001', app(EstimateService::class)->create($this->input($link))['reference']);
    }

    public function test_rollback_does_not_consume_a_number(): void
    {
        [$link] = $this->link(2027, 'ZEO-RAT');
        try {
            DB::transaction(function () use ($link) {
                app(EstimateService::class)->create($this->input($link));
                throw new \RuntimeException('Rollback test');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Rollback test', $e->getMessage());
        }
        $this->assertDatabaseCount('estimates', 0);
        $this->assertDatabaseCount('estimate_reference_sequences', 0);
        $this->assertSame('2027/ZEO-RAT/EST/0001', app(EstimateService::class)->create($this->input($link))['reference']);
    }

    public function test_invalid_or_inactive_assignment_is_rejected(): void
    {
        [$link] = $this->link(2027, 'ZEO-RAT');
        DB::table('offices')->where('code', 'ZEO-RAT')->update(['is_active' => false]);
        foreach ([$link, 999999] as $invalid) {
            try {
                app(EstimateService::class)->create($this->input($invalid));
                $this->fail('Invalid assignment should be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('aip_activity_office_id', $e->errors());
            }
        }
        $this->assertDatabaseCount('estimate_reference_sequences', 0);
    }

    public function test_old_records_without_reference_remain_accessible(): void
    {
        $id = DB::table('estimates')->insertGetId(['title' => 'Old estimate', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(); $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/estimates/'.$id)->assertOk()->assertInertia(fn ($page) => $page->where('estimate.reference', null));
    }
}
