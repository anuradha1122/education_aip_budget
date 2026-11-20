<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/password', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $newVersion = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated) {
            $user = \App\Models\User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => __('auth.password')]);
            }
            $version = (int) $user->access_version + 1;
            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'access_version' => $version,
                'remember_token' => \Illuminate\Support\Str::random(60),
            ])->save();
            $now = now();
            \Illuminate\Support\Facades\DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $user->id, 'actor_id' => $user->id, 'action' => 'password_self_changed',
                'old_values' => null, 'new_values' => json_encode(['password_changed' => true], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            return $version;
        });
        // Keep the session which made the change; older sessions fail the version check.
        $request->session()->put('account_access_version', $newVersion);
        $request->session()->regenerate();

        return back();
    }
}
