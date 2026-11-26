<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipDefaultFinancialYearTest extends TestCase
{
    use RefreshDatabase;

    private function year(int $year, bool $closed = false): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => $year, 'start_date' => "$year-01-01", 'end_date' => "$year-12-31",
            'is_closed' => $closed, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_current_open_sri_lanka_year_is_default_and_other_open_year_is_available(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 20:00:00', 'UTC'));
        $admin = User::factory()->create(['is_admin' => true]);
        $current = $this->year(2027);
        $future = $this->year(2028);
        $this->actingAs($admin)->get('/aip-activities')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('defaultFinancialYearId', $current)
                ->has('years', 2)->where('years.0.id', $future));
    }

    public function test_closed_or_missing_current_year_has_no_default(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 10:00:00', 'UTC'));
        $admin = User::factory()->create(['is_admin' => true]);
        $current = $this->year(2026, true);
        $this->year(2027);
        $this->actingAs($admin)->get('/aip-activities')->assertInertia(fn (Assert $page) => $page
            ->where('defaultFinancialYearId', null)->has('years', 1));
        DB::table('financial_years')->where('id', $current)->delete();
        $this->get('/aip-activities')->assertInertia(fn (Assert $page) => $page
            ->where('defaultFinancialYearId', null)->has('years', 1));
    }
}
