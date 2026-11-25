<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAccessAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');
        $users = DB::table('users as u')
            ->leftJoin('user_access_assignments as a', 'a.user_id', '=', 'u.id')
            ->leftJoin('offices as o', 'o.id', '=', 'a.office_id')
            ->leftJoin('user_officer_links as ul', 'ul.user_id', '=', 'u.id')
            ->leftJoin('officers as officer', 'officer.id', '=', 'ul.officer_id')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('u.name', 'like', '%'.$search.'%')->orWhere('u.email', 'like', '%'.$search.'%');
            }))
            ->orderBy('u.name')->orderBy('u.id')
            ->paginate(20, ['u.id', 'u.name', 'u.email', 'u.is_admin', 'u.is_active', 'u.can_import_aip', 'a.role', 'a.office_id', 'o.name as office_name', 'ul.officer_id', 'officer.name as officer_name'])
            ->withQueryString()
            ->through(function ($user) {
                $user->is_admin = (bool) $user->is_admin;
                $user->is_active = (bool) $user->is_active;
                $user->can_import_aip = (bool) $user->can_import_aip;
                return $user;
            });
        $offices = DB::table('offices')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $officers = DB::table('officers')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'office_id']);
        return Inertia::render('user-management/index', compact('users', 'offices', 'officers', 'search'));
    }

    public function store(Request $request, UserAccessAssignmentService $assignments): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(12)],
            'role' => ['required', Rule::in(['provincial_admin', 'zonal_admin', 'subject_director'])],
            'office_id' => ['nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($data, $request, $assignments) {
            $user = User::create([
                'name' => trim($data['name']), 'email' => trim($data['email']),
                'password' => Hash::make($data['password']),
            ]);
            $assignments->assign([
                'user_id' => $user->id, 'role' => $data['role'],
                'office_id' => $data['office_id'] ?? null,
            ], $request->user());
        });
        return redirect()->route('user-management.index');
    }
    public function updateAccess(Request $request, User $user, UserAccessAssignmentService $assignments): RedirectResponse
    {
        if ((bool) $user->is_admin) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role' => 'Existing administrator access cannot be changed on this page.',
            ]);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in(['provincial_admin', 'zonal_admin', 'subject_director'])],
            'office_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $assignments->assign([
            'user_id' => $user->id, 'role' => $data['role'],
            'office_id' => $data['office_id'] ?? null,
        ], $request->user());

        return redirect()->route('user-management.index');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($user, $request, $data) {
            $target = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if ($target === null || (bool) $target->is_admin || (int) $target->id === (int) $request->user()->id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['is_active' => 'Administrator accounts cannot be changed here.']);
            }
            $active = (bool) $data['is_active'];
            if ((bool) $target->is_active === $active) {
                return;
            }
            $now = now();
            $values = ['is_active' => $active, 'updated_at' => $now];
            if (! $active) {
                $values['remember_token'] = \Illuminate\Support\Str::random(60);
                $values['access_version'] = (int) $target->access_version + 1;
            }
            DB::table('users')->where('id', $target->id)->update($values);
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $target->id, 'actor_id' => $request->user()->id,
                'action' => $active ? 'activated' : 'deactivated',
                'old_values' => json_encode(['is_active' => (bool) $target->is_active], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['is_active' => $active], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('user-management.index');
    }

    public function updateDetails(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(12)],
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            $target = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if ($target === null || (bool) $target->is_admin) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'Existing administrator accounts cannot be changed on this page.',
                ]);
            }
            $name = trim($data['name']);
            $email = trim($data['email']);
            $passwordChanged = ! empty($data['password']);
            $emailChanged = $email !== $target->email;
            if ($name === $target->name && ! $emailChanged && ! $passwordChanged) {
                return;
            }
            $now = now();
            $values = ['name' => $name, 'email' => $email, 'updated_at' => $now];
            if ($passwordChanged) {
                // Invalidate links issued to the old email before changing credentials.
                \Illuminate\Support\Facades\Password::broker()->deleteToken(User::query()->findOrFail($target->id));
                $values['password'] = Hash::make($data['password']);
            }
            if ($emailChanged) {
                $values['email_verified_at'] = null;
            }
            if ($emailChanged || $passwordChanged) {
                $values['access_version'] = (int) $target->access_version + 1;
                $values['remember_token'] = \Illuminate\Support\Str::random(60);
            }
            DB::table('users')->where('id', $target->id)->update($values);
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $target->id, 'actor_id' => $request->user()->id,
                'action' => 'details_updated',
                'old_values' => json_encode(['name' => $target->name, 'email' => $target->email], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['name' => $name, 'email' => $email, 'password_changed' => $passwordChanged], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });

        return redirect()->route('user-management.index');
    }

}
