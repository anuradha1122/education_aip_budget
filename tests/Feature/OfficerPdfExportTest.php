<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_officer_pdf(): void
    {
        $this->get('/officers/export/pdf')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_officer_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/officers/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_officer_pdf(): void
    {
        $officer = $this->officer();

        $response = $this->actingAs($this->administrator())
            ->get('/officers/export/pdf');

        $response->assertOk();
        $response->assertDownload('officers.pdf');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
        ]);
    }

    public function test_the_pdf_view_displays_officer_details(): void
    {
        $officer = $this->officer();

        $this->view('pdf.officers', [
            'officers' => collect([$officer]),
        ])
            ->assertSee('Test Officer')
            ->assertSee('OFF-01')
            ->assertSee('Test Office')
            ->assertSee('DES-01')
            ->assertSee('Test Designation')
            ->assertSee('Inactive');
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $this->view('pdf.officers', [
            'officers' => collect(),
        ])->assertSee('No officers found.');
    }

    private function officer(): Officer
    {
        $office = Office::create([
            'code' => 'OFF-01',
            'name' => 'Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $designation = Designation::create([
            'code' => 'DES-01',
            'name' => 'Test Designation',
            'is_active' => true,
        ]);

        return Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => false,
        ]);
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