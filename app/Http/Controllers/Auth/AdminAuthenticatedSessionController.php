<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthenticatedSessionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole(Role::Administrator->value)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return view('auth.admin-login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(Role::Administrator);

        $request->session()->regenerate();

        return redirect()->to(AuthRedirect::adminUrl());
    }
}
