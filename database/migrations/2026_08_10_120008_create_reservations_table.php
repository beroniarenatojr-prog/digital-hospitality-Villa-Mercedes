<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('facility_id')->constrained('facilities')->restrictOnDelete();
            $table->string('reservation_code')->unique();
            $table->date('reservation_date');
            $table->dateTime('check_in');
            $table->dateTime('check_out');
            $table->unsignedInteger('guest_count');
            $table->text('special_requests')->nullable();
            
            $table->decimal('base_price', 10, 2);
            $table->decimal('additional_charges', 10, 2)->default(0.00);
            $table->decimal('total_price', 10, 2);
            
            $table->string('status')->default('pending'); // ReservationStatus enum
            $table->string('reservation_source')->default('online'); // ReservationSource enum
            $table->text('admin_notes')->nullable();
            
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('facility_id');
            $table->index('reservation_date');
            $table->index('check_in');
            $table->index('check_out');
            $table->index('status');
            $table->index('reservation_source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
