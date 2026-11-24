<?php

namespace App\Notifications\Reservation;

use App\Models\ReservationGroup;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupQuotationProvidedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReservationGroup $group,
        public readonly ?string $notes = null
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->group->loadMissing(['reservations.facility', 'user']);

        $lines = $this->group->reservations->map(function ($res) {
            $schedule = $res->check_in && $res->check_out
                ? $res->check_in->format('M d, Y g:i A') . ' to ' . $res->check_out->format('M d, Y g:i A')
                : 'Standard schedule';

            return [
                'name' => $res->facility?->name ?? 'Service',
                'schedule' => $schedule,
                'guests' => $res->guest_count,
            ];
        })->all();

        return (new MailMessage)
            ->subject('Quotation provided for your ' . config('resort.short_name') . ' booking ' . $this->group->group_code)
            ->markdown('mail.group-quotation-provided', [
                'guestName' => $this->group->guest_name,
                'groupCode' => $this->group->group_code,
                'policyVersion' => $this->group->policy_version,
                'confirmedPolicy' => $this->group->policy_version === config('resort.policy.confirmed_version'),
                'quotedTotal' => Money::of($this->group->total_amount),
                'depositRequired' => Money::of($this->group->deposit_required),
                'notes' => $this->notes,
                'lines' => $lines,
                'url' => route('customer.grouped-booking.quote-checkout', $this->group),
                'resortPhone' => config('resort.phone'),
                'resortPhoneHref' => config('resort.phone_href'),
            ]);
    }
}
