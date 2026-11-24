<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The two ends of a staff session, and the audit entries that outlive them.
 *
 * Signing in and signing out are the only staff actions with no record behind
 * them: nothing is created, nothing is approved, and the entry that says who was
 * at the desk — and when — has to be written here rather than by whichever
 * screen the person happened to be standing on.
 *
 * Signing out is also the one of the two that can arrive after its session is
 * already gone, so both the ordinary logout and the expired-page one come
 * through {@see end()}: the audit line is written first, while there may still
 * be someone to name, and the session is torn down after it.
 */
final class StaffSessionService
{
    public const REASON_SIGNED_OUT = 'signed_out';

    public const REASON_SESSION_EXPIRED = 'session_expired';

    public function started(User $staff): void
    {
        activity()
            ->causedBy($staff)
            ->event('staff_signed_in')
            ->log('Signed in to the back office.');
    }

    /**
     * Write the sign-out entry and end the session behind it.
     *
     * The actor is optional and only attributed when it is an administrator: a
     * logout whose session had already expired has nobody to name, and a guest
     * who posts to the staff door is not a staff sign-out. An unattributed
     * entry is more honest than a wrong name.
     */
    public function end(Request $request, ?User $actor, string $reason = self::REASON_SIGNED_OUT): void
    {
        $logger = activity()
            ->event('staff_signed_out')
            ->withProperties(['reason' => $reason]);

        if ($actor instanceof User && $actor->hasRole(Role::Administrator->value)) {
            $logger->causedBy($actor);
        }

        $logger->log(match ($reason) {
            self::REASON_SESSION_EXPIRED => 'Signed out of the back office after the page had expired.',
            default => 'Signed out of the back office.',
        });

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }
}
