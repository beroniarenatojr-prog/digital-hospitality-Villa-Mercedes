<?php

namespace App\Support;

use App\Models\Facility;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The guest's place in the reservation wizard.
 *
 * The wizard is one conversation across three steps, and everything a guest
 * typed used to live in the browser only. Closing the tab, opening a facility's
 * photos in another window, or taking a phone call mid-flow threw away the dates,
 * the guest count, the extra hours and every companion name, and the guest
 * started again from a blank step 1 — re-picking the arrival they had already
 * decided on, and retyping names they had already given.
 *
 * So a step that the server has already been asked to price leaves its work
 * behind, in the session of the account that did the asking, and the wizard
 * offers it back when the guest returns.
 *
 * Three things this deliberately is not:
 *
 *  - Not a hold. Nothing here reserves inventory; `calculate` revalidates
 *    availability and re-prices on the way back in.
 *  - Not the guest sheet. The identity fields the desk checks are read from the
 *    guest's profile, so the draft carries only what a guest typed by hand:
 *    companions and preferences.
 *  - Not shared. The session key names the account as well as the space, so a
 *    browser that changes hands cannot hand one account's booking to another.
 *
 * It also names the keys for the *emailed* way back (`pendingLinkKey`,
 * `linkPayloadKey`), because forgetting a booking and forgetting the link to it
 * are one action: `forget()` clears both, so no caller has to remember that a
 * resume link could outlive the booking it points at.
 */
final class BookingDraft
{
    /**
     * The wizard's prefill fields a draft may carry, in the order the steps
     * collect them. Anything else in the session entry is bookkeeping.
     */
    private const FIELDS = [
        'check_in',
        'check_out',
        'guest_count',
        'pax_tier',
        'early_check_in_hours',
        'late_check_out_hours',
        'other_guest_names',
        'special_requests',
    ];

    /** The datetime shape the wizard's date pickers read back. */
    public const DATETIME_FORMAT = 'Y-m-d H:i';

    /** `booking.draft.{account}.{space}` — the root the keys below are built from. */
    private const PREFIX = 'booking.draft';

    /** How many steps the wizard has, so a stored step can never point past it. */
    private const FIRST_STEP = 1;

    private const LAST_STEP = 3;

    /**
     * Remember the step a guest has just had priced.
     *
     * Called for a well-formed request even when the dates turn out to be taken:
     * a guest who has to go and think about other dates should not also have to
     * retype the party, and coming back to a reason beats coming back to a blank
     * form.
     *
     * @param  array<string, mixed>  $validated  the priced request, already validated
     */
    public static function remember(Facility $facility, array $validated, int $step): void
    {
        session()->put(self::key($facility), [
            'saved_at' => now()->toIso8601String(),
            'step' => self::clampStep($step),
            'check_in' => Carbon::parse($validated['check_in'])->format(self::DATETIME_FORMAT),
            'check_out' => Carbon::parse($validated['check_out'])->format(self::DATETIME_FORMAT),
            'guest_count' => (int) $validated['guestCount'],
            'pax_tier' => isset($validated['pax_tier']) ? (int) $validated['pax_tier'] : null,
            'early_check_in_hours' => (int) ($validated['early_check_in_hours'] ?? 0),
            'late_check_out_hours' => (int) ($validated['late_check_out_hours'] ?? 0),
            'other_guest_names' => GuestInformation::normalizeOtherGuests($validated['other_guest_names'] ?? []),
            'special_requests' => filled($validated['special_requests'] ?? null)
                ? trim((string) $validated['special_requests'])
                : null,
        ]);
    }

    /**
     * The draft for this space, or null when there is nothing worth resuming.
     *
     * @return array<string, mixed>|null
     */
    public static function load(Facility $facility): ?array
    {
        $draft = session(self::key($facility));

        if (! is_array($draft) || ! self::isResumable($draft)) {
            // An unusable draft is dropped rather than left to be re-checked on
            // every visit: it will never become usable again.
            self::forget($facility);

            return null;
        }

        return $draft;
    }

    /**
     * Forget this booking: the draft this browser is holding, and the emailed link
     * that could hand it back.
     *
     * Both callers mean the same thing by it — the booking is finished, or the
     * guest asked to start over — so the two live in one method rather than in
     * each caller's memory. A link that outlived its booking would offer a guest
     * a booking they had already made.
     */
    public static function forget(Facility $facility): void
    {
        self::forgetSession((int) $facility->id);

        if ($userId = Auth::id()) {
            self::forgetResumeLink((int) $userId, (int) $facility->id);
        }
    }

    /**
     * Drop the draft this browser is holding for one space, and nothing else.
     *
     * Split out for callers that know the space only by id — a list of unfinished
     * bookings does — and for the account-level link, which is keyed separately and
     * is cleared by `forget()`/`forgetResumeLink()`.
     */
    public static function forgetSession(int $facilityId): void
    {
        session()->forget(self::PREFIX.'.'.(Auth::id() ?? 'guest').'.'.$facilityId);
    }

    /**
     * Put a stored draft back into this session, exactly as the wizard reads it.
     *
     * Only the fields the wizard prefills are taken. A link is a way back to a
     * booking, not a way to hand the wizard anything else — and the guest is doing
     * something now, so the snapshot's `saved_at` is replaced rather than trusted.
     *
     * @param  array<string, mixed>  $draft
     */
    public static function restore(Facility $facility, array $draft): void
    {
        $restored = [
            'saved_at' => now()->toIso8601String(),
            'step' => self::clampStep((int) ($draft['step'] ?? self::LAST_STEP)),
        ];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $draft)) {
                $restored[$field] = $draft[$field];
            }
        }

        session()->put(self::key($facility), $restored);
    }

    /**
     * Whether this draft is still worth handing back: the wizard would otherwise
     * open on dates the server refuses, which reads as a bug rather than as a
     * booking the guest let go.
     *
     * @param  array<string, mixed>  $draft
     */
    public static function isResumable(array $draft): bool
    {
        $checkIn = $draft['check_in'] ?? null;

        if (! is_string($checkIn) || trim($checkIn) === '') {
            return false;
        }

        try {
            return Carbon::parse($checkIn)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Every unfinished booking this account is holding in this browser, keyed by
     * space, most recently saved first.
     *
     * This is what "My reservations" asks to show a guest the booking they walked
     * away from, so a draft that is no longer worth resuming is dropped here — the
     * same rule `load()` applies to one space, applied to all of them at once.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forAccount(int $userId): array
    {
        $held = session(self::accountKey($userId));

        if (! is_array($held)) {
            return [];
        }

        $drafts = [];

        foreach ($held as $facilityId => $draft) {
            $facilityId = (int) $facilityId;

            if (! is_array($draft) || ! self::isResumable($draft)) {
                self::forgetSession($facilityId);
                self::forgetResumeLink($userId, $facilityId);

                continue;
            }

            $drafts[$facilityId] = $draft;
        }

        uasort($drafts, fn (array $a, array $b): int => strcmp((string) ($b['saved_at'] ?? ''), (string) ($a['saved_at'] ?? '')));

        return $drafts;
    }

    /** Where every draft this account holds in this browser lives. */
    private static function accountKey(int $userId): string
    {
        return self::PREFIX.'.'.$userId;
    }

    /**
     * Where the resume link armed for this account and space is remembered, so a
     * job that was queued an hour ago can ask whether it is still the current one.
     */
    public static function pendingLinkKey(int $userId, int $facilityId): string
    {
        return 'booking.resume.pending.'.$userId.'.'.$facilityId;
    }

    /** Where the draft a resume link hands back is kept until it expires. */
    public static function linkPayloadKey(string $token): string
    {
        return 'booking.resume.payload.'.$token;
    }

    /**
     * What this account has links armed for, keyed by space.
     *
     * A cache has no way to list its keys, so the account has to keep the list
     * itself: without it, a draft whose browser is gone (a session that ended, a
     * phone the guest put down) would be reachable only through the email that
     * carried it.
     */
    public static function accountLinksKey(int $userId): string
    {
        return 'booking.resume.account.'.$userId;
    }

    public static function forgetResumeLink(int $userId, int $facilityId): void
    {
        $pending = Cache::get(self::pendingLinkKey($userId, $facilityId));

        if (is_string($pending) && $pending !== '') {
            Cache::forget(self::linkPayloadKey($pending));
        }

        Cache::forget(self::pendingLinkKey($userId, $facilityId));
    }

    /**
     * Draft values as wizard prefill, without overwriting anything the link
     * already said. A guest who arrives from the availability bar with dates has
     * stated their intent more recently than their own old draft.
     *
     * @param  array<string, mixed>  $prefill
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public static function mergeInto(array $prefill, array $draft): array
    {
        $merged = $prefill;

        foreach (self::FIELDS as $field) {
            if (filled($prefill[$field] ?? null) || ! array_key_exists($field, $draft)) {
                continue;
            }

            $merged[$field] = $draft[$field];
        }

        return $merged;
    }

    private static function clampStep(int $step): int
    {
        return max(self::FIRST_STEP, min(self::LAST_STEP, $step));
    }

    /**
     * Where this space's draft lives in the session.
     *
     * Public so that a caller — a test, most of all — can ask whether a draft
     * exists without hand-writing the key: `session(BookingDraft::key($facility))`.
     */
    public static function key(Facility $facility): string
    {
        return self::PREFIX.'.'.(Auth::id() ?? 'guest').'.'.$facility->id;
    }
}
