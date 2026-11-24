<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $url = $user instanceof User && $user->hasRole(Role::Administrator->value)
            ? AuthRedirect::adminUrl()
            : AuthRedirect::customerUrl();

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $url]);
        }

        return redirect()->to($url);
    }

    /**
     * Destroy an authenticated session.
     *
     * This is the guest door. Staff sign out through `admin.logout`, which knows
     * it is the back office without having to ask who was leaving.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
