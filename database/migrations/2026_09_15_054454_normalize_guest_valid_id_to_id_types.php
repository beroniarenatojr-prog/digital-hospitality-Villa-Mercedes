<?php

use App\Enums\GuestIdType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allowed = GuestIdType::values();

        DB::table('reservations')
            ->whereNotNull('guest_valid_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($allowed): void {
                foreach ($rows as $row) {
                    $value = (string) $row->guest_valid_id;

                    if (in_array($value, $allowed, true)) {
                        continue;
                    }

                    $mapped = match (true) {
                        str_starts_with(strtoupper($value), 'PHILID') => GuestIdType::PhilSys->value,
                        str_starts_with(strtoupper($value), 'PASSPORT') => GuestIdType::Passport->value,
                        str_starts_with(strtoupper($value), 'DL-') => GuestIdType::DriversLicense->value,
                        str_starts_with(strtoupper($value), 'UMID') => GuestIdType::Umid->value,
                        str_starts_with(strtoupper($value), 'PRC') => GuestIdType::Prc->value,
                        default => null,
                    };

                    DB::table('reservations')
                        ->where('id', $row->id)
                        ->update(['guest_valid_id' => $mapped]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }
};
