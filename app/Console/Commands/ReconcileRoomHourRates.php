<?php

namespace App\Console\Commands;

use App\Enums\RateType;
use App\Models\Facility;
use App\Services\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileRoomHourRates extends Command
{
    protected $signature = 'resort:reconcile-room-hour-rates {--apply : Apply the approved amounts; without this option only report differences}';

    protected $description = 'Audit room hourly fees against approved ₱200 early / ₱300 late amounts (dry-run by default)';

    public function handle(): int
    {
        $rows = [];
        $rooms = Facility::whereHas('facilityType', fn ($query) => $query->where('slug', 'room'))->orderBy('id')->get();
        $baseRows = [];
        foreach ($rooms as $room) {
            foreach ($room->rates()->where('is_active', true)->whereIn('type', [RateType::Base->value, RateType::Seasonal->value])->get() as $base) {
                $baseRows[] = [$room->name, $base->name, $base->amount, $base->unit, $base->effective_from, $base->effective_until];
            }
            foreach ([PricingService::EARLY_CHECK_IN_RATE_NAME => 200, PricingService::LATE_CHECK_OUT_RATE_NAME => 300] as $name => $amount) {
                $rates = $room->rates()->where('type', RateType::AdditionalCharge->value)->where('name', $name)->orderBy('id')->get();
                $active = $rates->where('is_active', true);
                $matches = $active->count() === 1 && (float) $active->first()->amount === (float) $amount && $active->first()->unit === 'per hour';
                $rows[] = [$room->name, $name, $active->map(fn ($rate) => $rate->amount.' '.$rate->unit)->implode('; ') ?: 'Missing', $amount.' per room hour', $matches ? 'Matches' : 'Needs reconciliation'];
                if ($this->option('apply') && ! $matches) {
                    DB::transaction(function () use ($room, $name, $amount) {
                        Facility::whereKey($room->id)->lockForUpdate()->firstOrFail();
                        $rates = $room->rates()->where('type', RateType::AdditionalCharge->value)->where('name', $name)->orderBy('id')->lockForUpdate()->get();
                        $canonical = $rates->firstWhere('is_active', true) ?? $rates->first();
                        $old = $rates->map->only(['id', 'amount', 'unit', 'is_active'])->all();
                        foreach ($rates as $rate) {
                            if ($rate->id !== $canonical?->id) $rate->update(['is_active' => false]);
                        }
                        $data = ['amount' => $amount, 'unit' => 'per hour', 'is_active' => true];
                        if ($canonical) $canonical->update($data);
                        else $room->rates()->create([...$data, 'type' => RateType::AdditionalCharge, 'name' => $name]);
                        activity()->performedOn($room)->event('room_hour_rates_reconciled')
                            ->withProperties(['name' => $name, 'old' => $old, 'approved_amount' => $amount])
                            ->log('Approved room hourly fee reconciled; saved bookings were not repriced.');
                    }, 5);
                }
            }
        }
        $this->info('Stored base/seasonal rates below are implementation starting points, not a resort-issued rate sheet. Owner review is required before activation.');
        $this->table(['Room', 'Stored rate', 'Amount', 'Unit', 'Effective from', 'Effective until'], $baseRows);
        $this->table(['Room', 'Charge', 'Current active rate(s)', 'Approved', 'Audit result'], $rows);
        $this->info($this->option('apply') ? 'Room rates reconciled. Historical bookings and hall records unchanged.' : 'Dry run only. No rates or resort records were changed. Use --apply after reviewing this audit.');

        return self::SUCCESS;
    }
}
