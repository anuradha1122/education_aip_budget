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

class AipActivityCommonVoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_different_vote_is_rejected_without_an_audit_event(): void
    {
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activity = AipActivity::create([
            'financial_year_id' => $year, 'activity_number' => '123', 'description' => 'Science seminar',
        ]);
        $otherActivity = AipActivity::create([
            'financial_year_id' => $year, 'activity_number' => '124', 'description' => 'Other activity',
        ]);
        $fund = Fund::create(['code' => 'PF', 'name' => 'Provincial fund', 'is_active' => true]);
        $firstVote = Vote::create(['code' => 'V1', 'description' => 'First vote', 'is_active' => true]);
        $secondVote = Vote::create(['code' => 'V2', 'description' => 'Second vote', 'is_active' => true]);
        $service = app(AipActivityFundingEntryService::class);

        $service->create(['aip_activity_id' => $activity->id, 'vote_id' => $firstVote->id, 'fund_id' => $fund->id]);
        try {
            $service->create(['aip_activity_id' => $activity->id, 'vote_id' => $secondVote->id, 'fund_id' => $fund->id]);
            $this->fail('A different vote for the same activity should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vote_id', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activity_funding_entries', 1);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 1);
        $this->assertDatabaseHas('aip_activity_funding_entries', [
            'aip_activity_id' => $activity->id, 'vote_id' => $firstVote->id, 'fund_id' => $fund->id,
        ]);

        // A different activity can use a different vote.
        $service->create(['aip_activity_id' => $otherActivity->id, 'vote_id' => $secondVote->id, 'fund_id' => $fund->id]);
        $this->assertDatabaseCount('aip_activity_funding_entries', 2);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 2);
    }
}
