<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_designation_pdf(): void
    {
        $this->get('/designations/export/pdf')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_designation_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/designations/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_designation_pdf(): void
    {
        $admin = $this->administrator();

        Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/designations/export/pdf');

        $response->assertOk();
        $response->assertDownload('designations.pdf');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );
    }

    public function test_the_pdf_view_displays_designation_details(): void
    {
        $designation = Designation::create([
            'code' => 'ADE',
            'name' => 'Assistant Director',
            'is_active' => false,
        ]);

        $this->view('pdf.designations', [
            'designations' => collect([$designation]),
        ])
            ->assertSee('ADE')
            ->assertSee('Assistant Director')
            ->assertSee('Inactive');
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $this->view('pdf.designations', [
            'designations' => collect(),
        ])->assertSee('No designations found.');
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