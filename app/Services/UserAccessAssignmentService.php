<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAccessAssignmentService
{
    /** Records an assignment only. It does not grant route permissions. */
    public function assign(array $input, User $actor): void
    {
        if (! (bool) $actor->is_admin) {
            throw new AuthorizationException('Only an administrator can assign access.');
        }

        $data = Validator::make($input, [
            'user_id' => ['required', 'integer', 'min:1'],
            'role' => ['required', Rule::in(['provincial_admin', 'zonal_admin', 'subject_director'])],
            'office_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        if ($data['role'] === 'provincial_admin' && ! empty($data['office_id'])) {
            throw ValidationException::withMessages(['office_id' => 'A provincial administrator has province-wide scope.']);
        }
        if ($data['role'] !== 'provincial_admin' && empty($data['office_id'])) {
            throw ValidationException::withMessages(['office_id' => 'An office is required for this role.']);
        }

        DB::transaction(function () use ($data, $actor) {
            $user = DB::table('users')->where('id', $data['user_id'])->lockForUpdate()->first();
            if ($user === null) {
                throw ValidationException::withMessages(['user_id' => 'The selected user does not exist.']);
            }
            $officeId = isset($data['office_id']) ? (int) $data['office_id'] : null;
            if ($officeId !== null && ! DB::table('offices')->where('id', $officeId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['office_id' => 'The selected office must exist and be active.']);
            }
            $current = DB::table('user_access_assignments')->where('user_id', $user->id)->first();
            $old = $current ? ['role' => $current->role, 'office_id' => $current->office_id === null ? null : (int) $current->office_id] : null;
            $new = ['role' => $data['role'], 'office_id' => $officeId];
            if ($old === $new) {
                return;
            }
            $now = now();
            // A changed role or office must not reactivate an earlier officer link.
            if ($current !== null) {
                $link = DB::table('user_officer_links')->where('user_id', $user->id)->first();
                if ($link !== null) {
                    DB::table('user_officer_links')->where('user_id', $user->id)->delete();
                    DB::table('user_access_assignment_audit_events')->insert([
                        'user_id' => $user->id, 'actor_id' => $actor->id, 'action' => 'officer_unlinked',
                        'old_values' => json_encode(['officer_id' => (int) $link->officer_id], JSON_THROW_ON_ERROR),
                        'new_values' => json_encode(['officer_id' => null], JSON_THROW_ON_ERROR),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            DB::table('user_access_assignments')->updateOrInsert(
                ['user_id' => $user->id],
                ['role' => $new['role'], 'office_id' => $officeId, 'updated_at' => $now] + ($current ? [] : ['created_at' => $now])
            );
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $user->id, 'actor_id' => $actor->id,
                'action' => $current ? 'updated' : 'created',
                'old_values' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR),
                'new_values' => json_encode($new, JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }
}
