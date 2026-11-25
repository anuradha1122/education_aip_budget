<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserImportPermissionController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['can_import_aip' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $user, $data) {
            $target = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if (! $target) {
                throw ValidationException::withMessages(['can_import_aip' => 'The selected user does not exist.']);
            }
            $allowed = (bool) $data['can_import_aip'];
            $provincial = DB::table('user_access_assignments')
                ->where('user_id', $target->id)->where('role', 'provincial_admin')
                ->whereNull('office_id')->exists();
            if ($allowed && ! (bool) $target->is_admin && ! $provincial) {
                throw ValidationException::withMessages(['can_import_aip' => 'Only system or province-wide provincial administrators can receive import permission.']);
            }
            if ($allowed && ! (bool) $target->can_import_aip && (int) $target->id === (int) $request->user()->id) {
                throw ValidationException::withMessages(['can_import_aip' => 'Another administrator must grant your import permission.']);
            }
            if ($allowed && ! (bool) $target->is_active) {
                throw ValidationException::withMessages(['can_import_aip' => 'The administrator must be active to receive import permission.']);
            }
            if ((bool) $target->can_import_aip === $allowed) return;
            $now = now();
            DB::table('users')->where('id', $target->id)->update(['can_import_aip' => $allowed, 'updated_at' => $now]);
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $target->id, 'actor_id' => $request->user()->id, 'action' => 'import_permission_updated',
                'old_values' => json_encode(['can_import_aip' => (bool) $target->can_import_aip], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['can_import_aip' => $allowed], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('user-management.index');
    }
}
