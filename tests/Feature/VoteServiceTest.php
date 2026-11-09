<?php

namespace Tests\Feature;

use App\Models\Vote;
use App\Services\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_vote_and_normalises_its_details(): void
    {
        $vote = app(VoteService::class)->create([
            'code' => '  vote-01  ',
            'description' => '  Test vote  ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('votes', [
            'id' => $vote->id,
            'code' => 'VOTE-01',
            'description' => 'Test vote',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_duplicate_code_after_normalisation(): void
    {
        $service = app(VoteService::class);

        $service->create([
            'code' => 'VOTE-01',
            'description' => 'First vote',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'code' => ' vote-01 ',
            'description' => 'Second vote',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_an_empty_description(): void
    {
        $this->expectException(ValidationException::class);

        app(VoteService::class)->create([
            'code' => 'VOTE-01',
            'description' => '   ',
            'is_active' => true,
        ]);
    }

    public function test_it_allows_a_vote_to_keep_its_code_when_updated(): void
    {
        $service = app(VoteService::class);

        $vote = $service->create([
            'code' => 'VOTE-01',
            'description' => 'Original description',
            'is_active' => true,
        ]);

        $service->update($vote, [
            'code' => ' vote-01 ',
            'description' => 'Updated description',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('votes', [
            'id' => $vote->id,
            'code' => 'VOTE-01',
            'description' => 'Updated description',
        ]);
    }

    public function test_it_deactivates_a_vote_without_deleting_it(): void
    {
        $service = app(VoteService::class);

        $vote = $service->create([
            'code' => 'VOTE-01',
            'description' => 'Test vote',
            'is_active' => true,
        ]);

        $service->deactivate($vote);

        $this->assertDatabaseHas('votes', [
            'id' => $vote->id,
            'code' => 'VOTE-01',
            'is_active' => false,
        ]);

        $this->assertSame(1, Vote::count());
    }
}