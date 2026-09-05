<?php

use App\Enums\TrainingApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applicants are usually walk-in scholarship seekers without a portal
     * account, so applications are stored against the batch rather than a user.
     */
    public function up(): void
    {
        Schema::create('training_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_batch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32);
            $table->string('address')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default(TrainingApplicationStatus::Pending->value);
            $table->text('staff_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['training_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_applications');
    }
};
