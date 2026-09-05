<?php

use App\Enums\TrainingBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TESDA enterprise-based training runs inside the working resort: a cohort
     * occupies real guest rooms, the cafe and sometimes a function hall for
     * weeks. Recording batches makes that occupancy visible to the availability
     * engine instead of living in a separate paper schedule.
     */
    public function up(): void
    {
        Schema::create('training_batches', function (Blueprint $table) {
            $table->id();
            $table->string('program');
            $table->string('cohort_code')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('daily_start_time')->nullable();
            $table->time('daily_end_time')->nullable();
            $table->unsignedSmallInteger('trainee_capacity')->default(0);
            $table->string('status')->default(TrainingBatchStatus::Planned->value);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_date']);
            $table->index('end_date');
        });

        Schema::create('training_batch_facility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_batch_id', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_batch_facility');
        Schema::dropIfExists('training_batches');
    }
};
