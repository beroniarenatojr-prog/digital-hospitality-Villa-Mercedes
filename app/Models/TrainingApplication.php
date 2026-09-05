<?php

namespace App\Models;

use App\Enums\TrainingApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingApplication extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'training_batch_id',
        'name',
        'email',
        'phone',
        'address',
        'message',
        'status',
        'staff_notes',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TrainingApplicationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrainingBatch, $this>
     */
    public function trainingBatch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class);
    }
}
