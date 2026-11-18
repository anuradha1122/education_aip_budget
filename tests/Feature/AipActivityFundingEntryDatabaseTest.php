<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Fund;
use App\Models\Vote;
use App\Services\AipActivityFundingEntryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityFundingEntryDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function activity(): AipActivity
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return AipActivity::create([
            'financial_year_id' => $yearId, 'activity_number' => '123',
            'description' => 'Science seminar',
        ]);
    }

    private function fund(string $code): Fund
    {
        return Fund::create(['code' => $code, 'name' => 'Example fund '.$code, 'is_active' => true]);
    }

    private function vote(string $code): Vote
    {
        return Vote::create(['code' => $code, 'description' => 'Example vote '.$code, 'is_active' => true]);
    }

    public function test_an_activity_can_have_one_fund_and_vote(): void
    {
        $activity = $this->activity();
        $fund = $this->fund('PF');
        $vote = $this->vote('VOTE-01');
        DB::table('aip_activity_funding_entries')->insert([
            'aip_activity_id' => $activity->id, 'vote_id' => $vote->id, 'fund_id' => $fund->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('aip_activity_funding_entries', [
            'aip_activity_id' => $activity->id, 'vote_id' => $vote->id, 'fund_id' => $fund->id,
        ]);
    }

    public function test_the_database_rejects_a_second_entry_for_an_activity(): void
    {
        $activity = $this->activity();
        $firstFund = $this->fund('PF');
        $secondFund = $this->fund('WB');
        $firstVote = $this->vote('VOTE-01');
        $secondVote = $this->vote('VOTE-02');
        DB::table('aip_activity_funding_entries')->insert([
            'aip_activity_id' => $activity->id, 'vote_id' => $firstVote->id, 'fund_id' => $firstFund->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(QueryException::class);
        DB::table('aip_activity_funding_entries')->insert([
            'aip_activity_id' => $activity->id, 'vote_id' => $secondVote->id, 'fund_id' => $secondFund->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_funding_entry_requires_an_existing_fund(): void
    {
        $activity = $this->activity();
        $vote = $this->vote('VOTE-01');
        $this->expectException(QueryException::class);
        DB::table('aip_activity_funding_entries')->insert([
            'aip_activity_id' => $activity->id, 'vote_id' => $vote->id, 'fund_id' => 999999,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_duplicate_service_request_is_rejected_without_an_audit_event(): void
    {
        $activity = $this->activity();
        $fund = $this->fund('PF');
        $vote = $this->vote('VOTE-01');
        $input = ['aip_activity_id' => $activity->id, 'vote_id' => $vote->id, 'fund_id' => $fund->id];
        $service = app(AipActivityFundingEntryService::class);
        $service->create($input);
        try {
            $service->create($input);
            $this->fail('The duplicate entry should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activity_funding_entries', 1);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 1);
    }
}
