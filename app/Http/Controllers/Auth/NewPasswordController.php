<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View|RedirectResponse
    {
        $email = $request->query('email');
        if (is_string($email) && $email !== '') {
            $user = Password::getUser(['email' => $email]);
            if (! $user || ! Password::tokenExists($user, $token)) {
                return $this->requestNewLink();
            }
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, &$resetUser) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    'profile_completed_at' => $user->profile_completed_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
                $resetUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->requestNewLink();
        }

        if ($resetUser instanceof User && $resetUser->needsContactDetails()) {
            Auth::login($resetUser);
            $request->session()->regenerate();

            return redirect()->route('profile.edit')
                ->with('success', 'Add your name and mobile number so the resort can reach you.');
        }

        return redirect()->route('login')->with('status', __($status));
    }

    private function requestNewLink(): RedirectResponse
    {
        return redirect()->route('password.request')
            ->withErrors(['email' => 'This reset link is invalid or has expired. Enter your email to request a new link.']);
    }
}
