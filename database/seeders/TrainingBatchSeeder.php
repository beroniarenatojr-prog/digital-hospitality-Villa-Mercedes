<?php

namespace Database\Seeders;

use App\Enums\TrainingBatchStatus;
use App\Models\Facility;
use App\Models\TrainingBatch;
use Illuminate\Database\Seeder;

class TrainingBatchSeeder extends Seeder
{
    /**
     * Two cohorts matching the programs advertised on the public training page,
     * so the training calendar is populated out of the box.
     */
    public function run(): void
    {
        $batches = [
            [
                'program' => 'Housekeeping NC II',
                'cohort_code' => 'HK-' . now()->year . '-01',
                'start_date' => now()->addDays(14)->toDateString(),
                'end_date' => now()->addDays(72)->toDateString(),
                'daily_start_time' => '08:00:00',
                'daily_end_time' => '17:00:00',
                'trainee_capacity' => 20,
                'status' => TrainingBatchStatus::Planned->value,
                'notes' => 'Practical sessions use the dormitory as the training accommodation area.',
                'facilities' => ['Dormitory'],
            ],
            [
                'program' => 'Barista NC II',
                'cohort_code' => 'BAR-' . now()->year . '-01',
                'start_date' => now()->addDays(21)->toDateString(),
                'end_date' => now()->addDays(51)->toDateString(),
                'daily_start_time' => '13:00:00',
                'daily_end_time' => '18:00:00',
                'trainee_capacity' => 15,
                'status' => TrainingBatchStatus::Planned->value,
                'notes' => 'Afternoon sessions in Opal Hall while the cafe serves guests.',
                'facilities' => ['Opal Hall'],
            ],
        ];

        foreach ($batches as $data) {
            $facilityNames = $data['facilities'];
            unset($data['facilities']);

            $batch = TrainingBatch::query()->firstOrCreate(
                ['cohort_code' => $data['cohort_code']],
                $data
            );

            $facilityIds = Facility::whereIn('name', $facilityNames)->pluck('id')->all();
            $batch->facilities()->syncWithoutDetaching($facilityIds);
        }
    }
}
