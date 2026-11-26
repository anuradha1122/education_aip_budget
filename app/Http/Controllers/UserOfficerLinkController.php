<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserOfficerLinkController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['officer_id' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($user, $data, $request) {
            $target = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $assignment = DB::table('user_access_assignments')->where('user_id', $user->id)->lockForUpdate()->first();
            $officer = DB::table('officers')->where('id', $data['officer_id'])->lockForUpdate()->first();
            if ($target !== null && ! (bool) $target->is_admin) abort_unless(app(\App\Services\UserManagementScope::class)->canManageTarget($request->user(), $target), 403);
            if ($target === null || (bool) $target->is_admin || $assignment === null || $assignment->role !== 'subject_director') {
                throw ValidationException::withMessages(['officer_id' => 'Select a non-admin user with the subject director role.']);
            }
            if ($officer === null || ! (bool) $officer->is_active || (string) $officer->office_id !== (string) $assignment->office_id || ! DB::table('offices')->where('id', $assignment->office_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['officer_id' => 'Select an active officer from the user’s active office.']);
            }
            $current = DB::table('user_officer_links')->where('user_id', $user->id)->first();
            if ($current !== null && (int) $current->officer_id === (int) $officer->id) return;
            $now = now();
            DB::table('user_officer_links')->updateOrInsert(['user_id' => $user->id], [
                'officer_id' => $officer->id, 'updated_at' => $now,
            ] + ($current ? [] : ['created_at' => $now]));
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $user->id, 'actor_id' => $request->user()->id, 'action' => 'officer_linked',
                'old_values' => $current ? json_encode(['officer_id' => (int) $current->officer_id], JSON_THROW_ON_ERROR) : null,
                'new_values' => json_encode(['officer_id' => (int) $officer->id], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('user-management.index');
    }
    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($user, $request) {
            $target = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if ($target === null || (bool) $target->is_admin) {
                throw ValidationException::withMessages(['officer_id' => 'Existing administrator accounts cannot be changed here.']);
            }
            abort_unless(app(\App\Services\UserManagementScope::class)->canManageTarget($request->user(), $target), 403);
            $link = DB::table('user_officer_links')->where('user_id', $user->id)->first();
            if ($link === null) return;
            $now = now();
            DB::table('user_officer_links')->where('user_id', $user->id)->delete();
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $user->id, 'actor_id' => $request->user()->id, 'action' => 'officer_unlinked',
                'old_values' => json_encode(['officer_id' => (int) $link->officer_id], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['officer_id' => null], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('user-management.index');
    }

}
