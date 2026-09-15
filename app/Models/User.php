<?php

namespace App\Models;

use App\Notifications\Auth\GuestPasswordResetNotification;
use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Traits\LogsModelActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsModelActivity, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'address',
        'nationality',
        'valid_id_type',
        'company',
        'password',
        'profile_completed_at',
        'terms_accepted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'date_of_birth' => 'date',
            'gender' => GuestGender::class,
            'valid_id_type' => GuestIdType::class,
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<Inquiry, $this>
     */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /**
     * @return HasMany<GuestReview, $this>
     */
    public function guestReviews(): HasMany
    {
        return $this->hasMany(GuestReview::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }

    public function isLightweight(): bool
    {
        return $this->profile_completed_at === null;
    }

    public function needsContactDetails(): bool
    {
        return ! filled($this->phone);
    }

    public function markProfileCompleted(): void
    {
        if ($this->profile_completed_at !== null) {
            return;
        }

        $this->forceFill(['profile_completed_at' => now()])->save();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new GuestPasswordResetNotification($token));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['remember_token'])
            ->logFillable();
    }
}
