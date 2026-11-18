<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Fund;
use App\Models\Vote;
use App\Services\AipActivityFundingEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityOneFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_activity_cannot_be_given_a_different_fund(): void
    {
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity = AipActivity::create([
            'financial_year_id' => $year,
            'activity_number' => '123',
            'description' => 'Science seminar',
        ]);

        $otherActivity = AipActivity::create([
            'financial_year_id' => $year,
            'activity_number' => '124',
            'description' => 'Other activity',
        ]);

        $vote = Vote::create([
            'code' => 'VOTE-01',
            'description' => 'Example vote',
            'is_active' => true,
        ]);

        $firstFund = Fund::create([
            'code' => 'PF',
            'name' => 'First fund',
            'is_active' => true,
        ]);

        $secondFund = Fund::create([
            'code' => 'WB',
            'name' => 'Second fund',
            'is_active' => true,
        ]);

        $service = app(AipActivityFundingEntryService::class);

        $service->create([
            'aip_activity_id' => $activity->id,
            'vote_id' => $vote->id,
            'fund_id' => $firstFund->id,
        ]);

        try {
            $service->create([
                'aip_activity_id' => $activity->id,
                'vote_id' => $vote->id,
                'fund_id' => $secondFund->id,
            ]);

            $this->fail('A different fund for the same activity should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_funding_entries', 1);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 1);

        $this->assertDatabaseHas('aip_activity_funding_entries', [
            'aip_activity_id' => $activity->id,
            'fund_id' => $firstFund->id,
        ]);

        // A different activity may use a different fund.
        $service->create([
            'aip_activity_id' => $otherActivity->id,
            'vote_id' => $vote->id,
            'fund_id' => $secondFund->id,
        ]);

        $this->assertDatabaseCount('aip_activity_funding_entries', 2);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 2);
    }
}