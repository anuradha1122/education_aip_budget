<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $rateLimitKey = 'profile-email-password:'.$request->user()->getAuthIdentifier();
        $invalidPassword = false;
        try {
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated, $rateLimitKey, &$invalidPassword) {
            $user = \App\Models\User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $old = ['name' => $user->name, 'email' => $user->email];
            $user->fill($validated);
            $emailChanged = $user->isDirty('email');
            if (! $user->isDirty(['name', 'email'])) {
                return ['user' => $user, 'email_changed' => false];
            }
            if ($emailChanged) {
                if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                    $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'current_password' => 'Too many incorrect attempts. Try again in '.$seconds.' seconds.',
                    ]);
                }
                $password = $request->input('current_password');
                if (! is_string($password) || $password === '' || ! \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                    $invalidPassword = true;
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'current_password' => 'Enter your current password to change your email address.',
                    ]);
                }
                $user->email_verified_at = null;
                $user->access_version = (int) $user->access_version + 1;
                $user->remember_token = \Illuminate\Support\Str::random(60);
            }
            $user->save();
            $now = now();
            \Illuminate\Support\Facades\DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $user->id, 'actor_id' => $user->id, 'action' => 'profile_updated',
                'old_values' => json_encode($old, JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['name' => $user->name, 'email' => $user->email], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            return ['user' => $user, 'email_changed' => $emailChanged];
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            // Record failures after rollback so the database cache store retains them.
            if ($invalidPassword) {
                \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 60);
            }
            throw $exception;
        }

        if ($result['email_changed']) {
            \Illuminate\Support\Facades\RateLimiter::clear($rateLimitKey);
            // Preserve only the session which made this change.
            $request->session()->put('account_access_version', (int) $result['user']->access_version);
            $request->session()->regenerate();
        }
        return to_route('profile.edit');
    }

}
