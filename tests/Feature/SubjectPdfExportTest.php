<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_subject_pdf(): void
    {
        $this->get('/subjects/export/pdf')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_subject_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/subjects/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_subject_pdf(): void
    {
        $response = $this->actingAs($this->administrator())
            ->get('/subjects/export/pdf');

        $response->assertOk();
        $response->assertDownload('subjects.pdf');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );
    }

    public function test_the_pdf_view_displays_subject_details(): void
    {
        $office = new Office;
        $office->code = 'OFF-01';
        $office->name = 'Test office';

        $subject = new Subject;
        $subject->code = 'SUB-01';
        $subject->name = 'Test subject';
        $subject->is_active = false;
        $subject->setRelation('office', $office);

        $this->view('pdf.subjects', [
            'subjects' => collect([$subject]),
        ])
            ->assertSee('OFF-01')
            ->assertSee('Test office')
            ->assertSee('SUB-01')
            ->assertSee('Test subject')
            ->assertSee('Inactive');
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $this->view('pdf.subjects', [
            'subjects' => collect(),
        ])->assertSee('No subjects found.');
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