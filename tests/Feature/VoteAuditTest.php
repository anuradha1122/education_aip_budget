<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_vote_creation_is_recorded_with_the_actor(): void
    {
        $actor = User::factory()->create();

        $vote = app(VoteService::class)->create([
            'code' => 'VOTE-01',
            'description' => 'First vote',
            'is_active' => true,
        ], $actor);

        $event = DB::table('vote_audit_events')
            ->where('vote_id', $vote->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('created', $event->action);
        $this->assertNull($event->old_values);

        $newValues = json_decode($event->new_values, true);

        $this->assertSame('VOTE-01', $newValues['code']);
        $this->assertSame('First vote', $newValues['description']);
        $this->assertTrue($newValues['is_active']);
    }

    public function test_vote_update_records_previous_and_new_values(): void
    {
        $actor = User::factory()->create();
        $service = app(VoteService::class);

        $vote = $service->create([
            'code' => 'VOTE-01',
            'description' => 'Original description',
            'is_active' => true,
        ], $actor);

        $service->update($vote, [
            'code' => 'VOTE-01',
            'description' => 'Updated description',
            'is_active' => false,
        ], $actor);

        $event = DB::table('vote_audit_events')
            ->where('vote_id', $vote->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('Original description', $oldValues['description']);
        $this->assertTrue($oldValues['is_active']);
        $this->assertSame('Updated description', $newValues['description']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_vote_does_not_create_an_update_event(): void
    {
        $actor = User::factory()->create();
        $service = app(VoteService::class);

        $vote = $service->create([
            'code' => 'VOTE-01',
            'description' => 'Test vote',
            'is_active' => true,
        ], $actor);

        $service->update($vote, [
            'code' => 'VOTE-01',
            'description' => 'Test vote',
            'is_active' => true,
        ], $actor);

        $this->assertDatabaseCount('vote_audit_events', 1);
    }
}