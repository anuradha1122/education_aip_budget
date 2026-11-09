<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotePdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_vote_pdf(): void
    {
        $this->get('/votes/export/pdf')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_vote_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/votes/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_vote_pdf(): void
    {
        $admin = $this->administrator();

        Vote::create([
            'code' => 'V-01',
            'description' => 'Test vote',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/votes/export/pdf');

        $response->assertOk();
        $response->assertDownload('votes.pdf');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );
    }

    public function test_the_pdf_view_displays_vote_details(): void
    {
        $vote = Vote::create([
            'code' => 'V-02',
            'description' => 'Inactive test vote',
            'is_active' => false,
        ]);

        $this->view('pdf.votes', [
            'votes' => collect([$vote]),
        ])
            ->assertSee('V-02')
            ->assertSee('Inactive test vote')
            ->assertSee('Inactive');
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $this->view('pdf.votes', [
            'votes' => collect(),
        ])->assertSee('No votes found.');
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }
}