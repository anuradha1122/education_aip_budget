<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DelegatedUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function office(string $code): int
    {
        return DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assigned(string $role, ?int $office, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => $role, 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        return $user;
    }

    public function test_zonal_admin_manages_only_subject_directors_of_own_office(): void
    {
        $one = $this->office('Z01'); $two = $this->office('Z02');
        $zonal = $this->assigned('zonal_admin', $one, 'Zonal manager');
        $own = $this->assigned('subject_director', $one, 'Own director');
        $other = $this->assigned('subject_director', $two, 'Other director');
        $peer = $this->assigned('zonal_admin', $one, 'Other manager');
        $this->actingAs($zonal)->get('/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('managementScope', 'zonal_admin')->has('users.data', 1)->where('users.data.0.id', $own->id)->has('offices', 1));
        $this->get('/user-management/'.$own->id.'/history')->assertOk();
        $this->get('/user-management/'.$other->id.'/history')->assertForbidden();
        $this->put('/user-management/'.$other->id.'/details', ['name' => 'Changed', 'email' => $other->email])->assertForbidden();
        $this->patch('/user-management/'.$other->id.'/status', ['is_active' => false])->assertForbidden();
        $this->delete('/user-management/'.$other->id.'/officer')->assertForbidden();
        $this->put('/user-management/'.$peer->id.'/details', ['name' => 'Changed', 'email' => $peer->email])->assertForbidden();
        $this->patch('/user-management/'.$own->id.'/import-permission', ['can_import_aip' => true])->assertForbidden();
        $this->put('/user-management/'.$own->id.'/access', ['role' => 'provincial_admin', 'office_id' => null])->assertForbidden();
        $this->put('/user-management/'.$own->id.'/access', ['role' => 'subject_director', 'office_id' => $two])->assertForbidden();
        $this->post('/user-management', ['name' => 'Forbidden', 'email' => 'forbidden@example.test', 'password' => 'valid-password-123', 'password_confirmation' => 'valid-password-123', 'role' => 'zonal_admin', 'office_id' => $one])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'forbidden@example.test']);
        $this->post('/user-management', ['name' => 'New director', 'email' => 'new-director@example.test', 'password' => 'valid-password-123', 'password_confirmation' => 'valid-password-123', 'role' => 'subject_director', 'office_id' => $one])->assertRedirect('/user-management')->assertSessionHasNoErrors();
        $new = User::where('email', 'new-director@example.test')->firstOrFail();
        $this->assertDatabaseHas('user_access_assignments', ['user_id' => $new->id, 'role' => 'subject_director', 'office_id' => $one]);
        $this->put('/user-management/'.$own->id.'/details', ['name' => 'Updated director', 'email' => $own->email, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $own->fresh()->password));
        $this->patch('/user-management/'.$own->id.'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $own->fresh()->is_active);
        $this->assertSame('Other director', $other->fresh()->name);
    }

    public function test_zonal_access_review_and_excel_are_scoped(): void
    {
        $one = $this->office('A01'); $two = $this->office('A02');
        $manager = $this->assigned('zonal_admin', $one, 'Manager');
        $own = $this->assigned('subject_director', $one, 'Visible director');
        $this->assigned('subject_director', $two, 'Hidden director');
        $this->actingAs($manager)->get('/user-management/access-review')->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $own->id));
        $response = $this->get('/user-management/access-review/export/excel')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'scoped-users-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            try { $this->assertSame('Visible director', $book->getActiveSheet()->getCell('A2')->getValue()); $this->assertNull($book->getActiveSheet()->getCell('A3')->getValue()); }
            finally { $book->disconnectWorksheets(); }
        } finally { unlink($path); }
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('user-access-review.pdf')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(fn ($view, $data) => $view === 'pdf.user-access-review'
            && $data['rows']->count() === 1 && (int) $data['rows']->first()->id === $own->id)->andReturn($pdf);
        $this->get('/user-management/access-review/export/pdf')->assertOk();
    }

    public function test_zonal_officer_linking_and_inactive_office_restrictions(): void
    {
        $one = $this->office('L01'); $two = $this->office('L02');
        $manager = $this->assigned('zonal_admin', $one, 'Manager');
        $director = $this->assigned('subject_director', $one, 'Director');
        $foreign = $this->assigned('subject_director', $two, 'Other director');
        $designation = DB::table('designations')->insertGetId(['code' => 'L01', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = DB::table('officers')->insertGetId(['office_id' => $one, 'designation_id' => $designation, 'name' => 'Own officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $otherOfficer = DB::table('officers')->insertGetId(['office_id' => $two, 'designation_id' => $designation, 'name' => 'Other officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($manager)->put('/user-management/'.$director->id.'/officer', ['officer_id' => $officer])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_officer_links', ['user_id' => $director->id, 'officer_id' => $officer]);
        $this->put('/user-management/'.$director->id.'/officer', ['officer_id' => $otherOfficer])->assertSessionHasErrors('officer_id');
        $this->put('/user-management/'.$foreign->id.'/officer', ['officer_id' => $otherOfficer])->assertForbidden();
        $this->delete('/user-management/'.$director->id.'/officer')->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('user_officer_links', ['user_id' => $director->id]);
        DB::table('offices')->where('id', $one)->update(['is_active' => false]);
        $this->get('/user-management')->assertForbidden();
    }

    public function test_provincial_admin_manages_users_across_offices_but_cannot_change_system_admin(): void
    {
        $one = $this->office('P01'); $two = $this->office('P02');
        $province = $this->assigned('provincial_admin', null, 'Province manager');
        $zonal = $this->assigned('zonal_admin', $one, 'Zone manager');
        $director = $this->assigned('subject_director', $two, 'Director');
        $system = User::factory()->create(); $system->forceFill(['is_admin' => true])->save();
        $this->actingAs($province)->get('/user-management')->assertOk();
        $this->put('/user-management/'.$zonal->id.'/details', ['name' => 'Updated zone', 'email' => $zonal->email])->assertSessionHasNoErrors();
        $this->patch('/user-management/'.$director->id.'/status', ['is_active' => false])->assertSessionHasNoErrors();
        $this->put('/user-management/'.$system->id.'/details', ['name' => 'Changed system', 'email' => $system->email])->assertSessionHasErrors('email');
        $this->patch('/user-management/'.$system->id.'/status', ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->assertNotSame('Changed system', $system->fresh()->name);
        $this->assertTrue((bool) $system->fresh()->is_active);
        $this->patch('/user-management/'.$province->id.'/import-permission', ['can_import_aip' => true])->assertForbidden();
    }
}
