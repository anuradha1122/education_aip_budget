<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_subject_can_be_saved_for_an_office(): void
    {
        $office = $this->office('OFFICE-01');

        $subject = Subject::create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
        ]);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ]);

        $this->assertSame($office->id, $subject->office->id);
    }

    public function test_the_same_code_can_be_used_by_different_offices(): void
    {
        $firstOffice = $this->office('OFFICE-01');
        $secondOffice = $this->office('OFFICE-02');

        Subject::create([
            'office_id' => $firstOffice->id,
            'code' => 'SUB-01',
            'name' => 'First subject',
        ]);

        Subject::create([
            'office_id' => $secondOffice->id,
            'code' => 'SUB-01',
            'name' => 'Second subject',
        ]);

        $this->assertDatabaseCount('subjects', 2);
    }

    public function test_a_subject_code_must_be_unique_within_an_office(): void
    {
        $office = $this->office('OFFICE-01');

        Subject::create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'First subject',
        ]);

        $this->expectException(QueryException::class);

        Subject::create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Duplicate subject',
        ]);
    }

    private function office(string $code): Office
    {
        return Office::create([
            'code' => $code,
            'name' => 'Test office '.$code,
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }
}