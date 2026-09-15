<?php

namespace App\Models;

use Database\Factories\GuestReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GuestReview extends Model
{
    /** @use HasFactory<GuestReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reservation_id',
        'rating',
        'body',
        'stay_label',
        'is_published',
        'featured_on_home',
        'published_at',
        'public_consent', 'moderated_at', 'moderated_by', 'moderation_reason', 'reviewed_reservation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'public_consent' => 'boolean', 'moderated_at' => 'datetime',
            'is_published' => 'boolean',
            'featured_on_home' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where(fn ($q) => $q->whereNull('public_consent')->orWhere('public_consent', true));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleOnPublicSite(Builder $query): Builder
    {
        $query->published();

        if (! config('resort.demo_content')) {
            $query->whereHas('user', function (Builder $userQuery) {
                $userQuery->where('email', 'not like', '%@villamercedes.test');
            });
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured_on_home', true);
    }

    public function guestName(): string
    {
        $name = trim($this->user?->name ?: 'Guest');
        if ($this->public_consent === null) return $name;
        $parts = preg_split('/\s+/', $name);
        return $parts[0].(count($parts) > 1 ? ' '.Str::substr((string) end($parts), 0, 1).'.' : '');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->guestName())) ?: [];
        $first = Str::substr($parts[0] ?? 'G', 0, 1);
        $last = count($parts) > 1 ? Str::substr((string) end($parts), 0, 1) : '';

        return Str::upper($first.$last);
    }

    public function stayCaption(): ?string
    {
        $date = $this->stayOccurredAt();
        $when = $date?->timezone(config('app.timezone'))->format('F Y');
        $parts = array_filter([$this->stay_label, $when]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function stayOccurredAt(): ?Carbon
    {
        $now = now();

        foreach ([
            $this->reservation?->checked_out_at,
            $this->reservation?->completed_at,
            $this->reservation?->check_in,
        ] as $value) {
            if ($value && $value->lte($now)) {
                return $value;
            }
        }

        return $this->published_at;
    }
}
