<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /** @return 'success'|'warning'|'danger'|'info'|'neutral'|'muted' */
    public function tone(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
