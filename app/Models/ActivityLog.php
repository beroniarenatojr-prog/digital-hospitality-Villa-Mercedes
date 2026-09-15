<?php

namespace App\Models;

use App\Enums\ActivityActionType;
use App\Enums\Role;
use App\Exceptions\Admin\ImmutableActivityLogException;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new ImmutableActivityLogException('Activity log entries cannot be modified.');
        });

        static::deleting(function (): void {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            throw new ImmutableActivityLogException('Activity log entries cannot be deleted.');
        });
    }

    public function actorName(): string
    {
        return $this->causer?->name ?? 'System';
    }

    public function actionType(): ?ActivityActionType
    {
        return ActivityActionType::fromEvent($this->event, $this->description);
    }

    public function actionLabel(): string
    {
        return $this->actionType()?->label()
            ?? (filled($this->event) ? str_replace('_', ' ', ucfirst((string) $this->event)) : 'Action');
    }

    public function subjectLabel(): ?string
    {
        $subject = $this->subject;

        if ($subject === null) {
            if (! filled($this->subject_type)) {
                return null;
            }

            return class_basename((string) $this->subject_type).' #'.$this->subject_id;
        }

        return match ($subject::class) {
            Reservation::class => $subject->reservation_code,
            Payment::class => 'Payment #'.$subject->getKey(),
            Facility::class => $subject->name,
            Rate::class => $subject->name,
            Inquiry::class => $subject->subject,
            Faq::class => $subject->question,
            FaqCategory::class => $subject->name,
            PagePhoto::class => 'Page photo',
            User::class => $subject->name,
            default => class_basename($subject).' #'.$subject->getKey(),
        };
    }

    public function subjectUrl(): ?string
    {
        $subject = $this->subject;

        if ($subject === null) {
            return null;
        }

        return match ($subject::class) {
            Reservation::class => route('admin.reservations.show', $subject),
            Payment::class => route('admin.payments.show', $subject),
            Facility::class => route('admin.facilities.show', $subject),
            Rate::class => route('admin.rates.show', $subject),
            Inquiry::class => route('admin.inquiries.show', $subject),
            User::class => $subject->hasRole(Role::Customer->value)
                ? route('admin.customers.show', $subject)
                : null,
            default => null,
        };
    }
}
