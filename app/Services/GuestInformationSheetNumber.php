<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class GuestInformationSheetNumber
{
    /** Render under the allocation lock: failed rendering consumes no number. */
    public function issue(Reservation $reservation, callable $render): mixed
    {
        return DB::transaction(function () use ($reservation, $render) {
            $sequence = DB::table('document_sequences')->where('name', 'guest_information_sheet')->lockForUpdate()->first();
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === ReservationStatus::Approved, 404);

            if ($locked->guest_sheet_control_number === null) {
                $locked->guest_sheet_control_number = $sequence->next_number;
                $locked->saveQuietly();
                DB::table('document_sequences')->where('name', 'guest_information_sheet')->update(['next_number' => $sequence->next_number + 1]);
            }

            return $render($locked);
        }, 5);
    }
}
