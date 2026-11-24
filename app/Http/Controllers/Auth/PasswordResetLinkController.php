<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Support\OutboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        if (! OutboundMail::isConfigured()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Password reset emails are unavailable right now. Please try again later or call the resort.']);
        }

        try {
            Password::sendResetLink(
                $request->only('email'),
                function ($user, string $token) use ($request): void {
                    if ((bool) config('auth.passwords.users.reveal_link', false)) {
                        $request->session()->flash('password_reset_url', route('password.reset', [
                            'token' => $token,
                            'email' => $user->getEmailForPasswordReset(),
                        ], false));
                    }

                    $user->sendPasswordResetNotification($token);
                },
            );
        } catch (Throwable $e) {
            report($e);

            $request->session()->forget('password_reset_url');
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', 'If this email belongs to an account, check its inbox and spam folder for a password reset link.');
    }
}
