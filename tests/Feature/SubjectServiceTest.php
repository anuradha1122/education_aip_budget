<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubjectServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_subject_and_normalises_details(): void
    {
        $office = $this->office('OFF-01');

        $subject = app(SubjectService::class)->create([
            'office_id' => $office->id,
            'code' => ' sub-01 ',
            'name' => ' Test subject ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_duplicate_code_in_the_same_office(): void
    {
        $office = $this->office('OFF-01');
        $service = app(SubjectService::class);

        $service->create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'First subject',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'office_id' => $office->id,
            'code' => ' sub-01 ',
            'name' => 'Duplicate subject',
            'is_active' => true,
        ]);
    }

    public function test_it_allows_the_same_code_in_a_different_office(): void
    {
        $firstOffice = $this->office('OFF-01');
        $secondOffice = $this->office('OFF-02');
        $service = app(SubjectService::class);

        foreach ([$firstOffice, $secondOffice] as $office) {
            $service->create([
                'office_id' => $office->id,
                'code' => 'SUB-01',
                'name' => 'Test subject',
                'is_active' => true,
            ]);
        }

        $this->assertDatabaseCount('subjects', 2);
    }

    public function test_it_rejects_an_office_that_does_not_exist(): void
    {
        $this->expectException(ValidationException::class);

        app(SubjectService::class)->create([
            'office_id' => 999999,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ]);
    }

    public function test_it_allows_a_subject_to_keep_its_code_when_updated(): void
    {
        $office = $this->office('OFF-01');
        $service = app(SubjectService::class);

        $subject = $service->create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Original name',
            'is_active' => true,
        ]);

        $service->update($subject, [
            'office_id' => $office->id,
            'code' => ' sub-01 ',
            'name' => 'Updated name',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'code' => 'SUB-01',
            'name' => 'Updated name',
        ]);
    }

    public function test_it_deactivates_a_subject_without_deleting_it(): void
    {
        $office = $this->office('OFF-01');
        $service = app(SubjectService::class);

        $subject = $service->create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ]);

        $service->deactivate($subject);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'is_active' => false,
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