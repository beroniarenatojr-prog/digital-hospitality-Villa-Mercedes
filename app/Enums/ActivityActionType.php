<?php

namespace App\Enums;

enum ActivityActionType: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Approve = 'approve';
    case SignIn = 'sign_in';
    case SignOut = 'sign_out';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Update => 'Update',
            self::Delete => 'Delete',
            self::Approve => 'Approve',
            self::SignIn => 'Sign in',
            self::SignOut => 'Sign out',
        };
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return match ($this) {
            self::Create => [
                'created',
                'facility_created',
                'rate_created',
                'page_photos_uploaded',
                'facility_images_uploaded',
                'payment_destination_added',
                'onsite_payment_recorded',
            ],
            self::Update => [
                'updated',
                'facility_updated',
                'rate_updated',
                'page_photo_updated',
                'amenities_assigned',
                'package_components_synced',
                'rescheduled',
                'status_changed',
                'facility_status_changed',
                'rate_activated',
                'rate_deactivated',
                'checked_in',
                'checked_out',
                'responded',
                'payment_rejected',
                'payment_destination_updated',
                'guest_review_moderated',
                'deposit_top_up_requested',
                'deposit_hold_expired',
                'late_checkout_declined',
                'room_hour_rates_reconciled',
            ],
            self::Delete => [
                'facility_deleted',
                'page_photo_deleted',
                'facility_image_deleted',
            ],
            self::Approve => [
                'payment_verified',
                'late_checkout_approved',
            ],
            self::SignIn => [
                'staff_signed_in',
            ],
            self::SignOut => [
                'staff_signed_out',
            ],
        };
    }

    /**
     * Gateway or unattended events that still belong in the staff log.
     *
     * A sign-out belongs here because it is the one staff action that can arrive
     * after its own session has died: there is nobody left to attribute it to,
     * and an unattributed entry is a truer record than a missing one — the desk
     * changed hands at that minute either way.
     *
     * @return list<string>
     */
    public static function systemEvents(): array
    {
        return ['payment_verified', 'deposit_hold_expired', 'staff_signed_out', 'room_hour_rates_reconciled'];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromEvent(?string $event, ?string $description = null): ?self
    {
        if (self::isApproval($event, $description)) {
            return self::Approve;
        }

        foreach (self::cases() as $type) {
            if (in_array($event, $type->events(), true)) {
                return $type;
            }
        }

        return null;
    }

    public static function isApproval(?string $event, ?string $description = null): bool
    {
        if (in_array($event, ['payment_verified', 'late_checkout_approved'], true)) {
            return true;
        }

        return $event === 'status_changed'
            && (str_contains((string) $description, 'to Approved') || str_contains((string) $description, 'to Confirmed'));
    }
}
