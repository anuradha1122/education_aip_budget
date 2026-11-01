<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficePdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    public function test_a_guest_cannot_download_the_pdf(): void
    {
        $this->get('/offices/export/pdf')
            ->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_download_the_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/offices/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_a_pdf(): void
    {
        Office::create([
            'code' => 'ZONE-01',
            'name' => 'Example Zonal Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/offices/export/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload('offices.pdf');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );
    }

    public function test_the_pdf_view_displays_office_details(): void
    {
        $office = Office::create([
            'code' => 'ZONE-02',
            'name' => 'Example Inactive Office',
            'type' => 'zonal',
            'is_active' => false,
        ]);

        $this->view('pdf.offices', [
            'offices' => collect([$office]),
        ])
            ->assertSee('ZONE-02')
            ->assertSee('Example Inactive Office')
            ->assertSee('Zonal')
            ->assertSee('Inactive');
    }
}
