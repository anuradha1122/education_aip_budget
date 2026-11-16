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

class AipActivityFundingEntryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activity(bool $closed = false): AipActivity
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_closed' => $closed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AipActivity::create([
            'financial_year_id' => $yearId,
            'activity_number' => '123',
            'description' => 'Science seminar',
        ]);
    }

    private function vote(): Vote
    {
        return Vote::create([
            'code' => 'VOTE-01',
            'description' => 'Example vote',
            'is_active' => true,
        ]);
    }

    private function fund(): Fund
    {
        return Fund::create([
            'code' => 'PF',
            'name' => 'Example fund',
            'is_active' => true,
        ]);
    }

    public function test_it_saves_an_activity_vote_and_fund(): void
    {
        $activity = $this->activity();
        $vote = $this->vote();
        $fund = $this->fund();

        $id = app(AipActivityFundingEntryService::class)->create([
            'aip_activity_id' => $activity->id,
            'vote_id' => $vote->id,
            'fund_id' => $fund->id,
        ]);

        $this->assertDatabaseHas('aip_activity_funding_entries', [
            'id' => $id,
            'aip_activity_id' => $activity->id,
            'vote_id' => $vote->id,
            'fund_id' => $fund->id,
        ]);
    }

    public function test_it_rejects_a_closed_financial_year(): void
    {
        $activity = $this->activity(true);
        $vote = $this->vote();
        $fund = $this->fund();

        try {
            app(AipActivityFundingEntryService::class)->create([
                'aip_activity_id' => $activity->id,
                'vote_id' => $vote->id,
                'fund_id' => $fund->id,
            ]);

            $this->fail('A closed financial year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_it_rejects_a_missing_activity(): void
    {
        $vote = $this->vote();
        $fund = $this->fund();

        try {
            app(AipActivityFundingEntryService::class)->create([
                'aip_activity_id' => 999999,
                'vote_id' => $vote->id,
                'fund_id' => $fund->id,
            ]);

            $this->fail('A missing activity should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_it_rejects_a_missing_vote(): void
    {
        $activity = $this->activity();
        $fund = $this->fund();

        try {
            app(AipActivityFundingEntryService::class)->create([
                'aip_activity_id' => $activity->id,
                'vote_id' => 999999,
                'fund_id' => $fund->id,
            ]);

            $this->fail('A missing vote should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vote_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }

    public function test_it_rejects_a_missing_fund(): void
    {
        $activity = $this->activity();
        $vote = $this->vote();

        try {
            app(AipActivityFundingEntryService::class)->create([
                'aip_activity_id' => $activity->id,
                'vote_id' => $vote->id,
                'fund_id' => 999999,
            ]);

            $this->fail('A missing fund should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
    }
}