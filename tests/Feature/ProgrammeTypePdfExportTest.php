<?php

namespace Tests\Feature;

use App\Models\ProgrammeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammeTypePdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_the_programme_type_pdf(): void
    {
        $this->get('/programme-types/export/pdf')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_download_the_programme_type_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/programme-types/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_the_programme_type_pdf(): void
    {
        ProgrammeType::create([
            'code' => 'TYPE-01',
            'name' => 'Test type',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/programme-types/export/pdf');

        $response->assertOk();
        $response->assertDownload('programme-types.pdf');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent()
        );
    }

    public function test_the_pdf_view_displays_programme_type_details(): void
    {
        $programmeType = ProgrammeType::create([
            'code' => 'TYPE-02',
            'name' => 'Inactive test type',
            'is_active' => false,
        ]);

        $this->view('pdf.programme-types', [
            'programmeTypes' => collect([$programmeType]),
        ])
            ->assertSee('TYPE-02')
            ->assertSee('Inactive test type')
            ->assertSee('Inactive');
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $this->view('pdf.programme-types', [
            'programmeTypes' => collect(),
        ])->assertSee('No programme types found.');
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