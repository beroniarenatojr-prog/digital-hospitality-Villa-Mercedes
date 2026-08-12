<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // cast to RateType enum
            $table->decimal('amount', 10, 2);
            $table->string('unit')->nullable(); // e.g., "per hour", "per night"
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('facility_id');
            $table->index('type');
            $table->index('is_active');
            $table->index(['facility_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};
