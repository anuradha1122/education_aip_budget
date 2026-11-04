<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class FundPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_a_guest_cannot_download_the_fund_pdf(): void
    {
        $this->get('/funds/export/pdf')
            ->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_download_the_fund_pdf(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/funds/export/pdf')
            ->assertForbidden();
    }

    public function test_an_administrator_can_download_a_fund_pdf(): void
    {
        Fund::create([
            'code' => 'PF',
            'name' => 'Provincial Fund',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->administrator())
            ->get('/funds/export/pdf');

        $response->assertOk();
        $response->assertDownload('fund-list.pdf');

        $this->assertStringStartsWith(
            'application/pdf',
            $response->headers->get('Content-Type')
        );

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_the_pdf_view_displays_fund_details(): void
    {
        $funds = collect([
            new Fund([
                'code' => 'PF',
                'name' => 'Provincial Fund',
                'is_active' => true,
            ]),
            new Fund([
                'code' => 'TEST',
                'name' => 'Test Fund',
                'is_active' => false,
            ]),
        ]);

        $html = View::make('pdf.funds', [
            'funds' => $funds,
        ])->render();

        $this->assertStringContainsString('Provincial Fund', $html);
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('Test Fund', $html);
        $this->assertStringContainsString('Inactive', $html);
    }

    public function test_the_pdf_view_handles_an_empty_list(): void
    {
        $html = View::make('pdf.funds', [
            'funds' => collect(),
        ])->render();

        $this->assertStringContainsString('No funds found.', $html);
    }
}
