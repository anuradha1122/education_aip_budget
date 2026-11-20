<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $account = $user === null ? null : \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->getAuthIdentifier())->first(['is_active', 'access_version']);
        $sessionVersion = $request->session()->get('account_access_version', 0);
        if ($user !== null && ($account === null || ! (bool) $account->is_active || (int) $sessionVersion !== (int) $account->access_version)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login');
        }
        return $next($request);
    }
}
