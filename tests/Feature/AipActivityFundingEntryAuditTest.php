<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Fund;
use App\Models\User;
use App\Models\Vote;
use App\Services\AipActivityFundingEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityFundingEntryAuditTest extends TestCase
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

    public function test_creation_records_the_actor_and_values(): void
    {
        $activity = $this->activity();
        $vote = $this->vote();
        $fund = $this->fund();
        $actor = User::factory()->create();

        $entryId = app(AipActivityFundingEntryService::class)->create([
            'aip_activity_id' => $activity->id,
            'vote_id' => $vote->id,
            'fund_id' => $fund->id,
        ], $actor);

        $event = DB::table('aip_funding_entry_audit_events')
            ->where('funding_entry_id', $entryId)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('created', $event->action);

        $values = json_decode(
            $event->new_values,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertEquals($activity->id, $values['aip_activity_id']);
        $this->assertEquals(
            $activity->financial_year_id,
            $values['financial_year_id']
        );
        $this->assertEquals($vote->id, $values['vote_id']);
        $this->assertEquals($fund->id, $values['fund_id']);
    }

    public function test_rejected_entry_does_not_create_an_audit_event(): void
    {
        $activity = $this->activity(true);
        $vote = $this->vote();
        $fund = $this->fund();

        try {
            app(AipActivityFundingEntryService::class)->create([
                'aip_activity_id' => $activity->id,
                'vote_id' => $vote->id,
                'fund_id' => $fund->id,
            ], User::factory()->create());

            $this->fail('A closed year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 0);
    }
}