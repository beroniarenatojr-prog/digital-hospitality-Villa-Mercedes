<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Services\RegistrationOtpService;
use App\Support\AuthRedirect;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RegistrationOtpService $registrationOtpService,
    ) {}

    public function create(): View
    {
        return view('auth.register', [
            'step' => $this->registrationOtpService->registrationStep(),
            'pendingEmail' => $this->registrationOtpService->pendingEmail(),
            'verifiedEmail' => $this->registrationOtpService->verifiedEmail(),
            'resendAvailableIn' => $this->registrationOtpService->resendAvailableIn(),
            'otpDigits' => max(4, (int) config('otp.digits')),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->authService->register($request->validated());

        $this->registrationOtpService->consume($user->email);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->to(AuthRedirect::customerUrl());
    }
}
