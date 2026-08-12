<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method'); // PaymentMethod enum
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->string('proof_path')->nullable();
            
            $table->string('status')->default('pending'); // PaymentStatus enum
            
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('reservation_id');
            $table->index('status');
            $table->index('method');
            $table->index('reference_number');
            $table->index('verified_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
