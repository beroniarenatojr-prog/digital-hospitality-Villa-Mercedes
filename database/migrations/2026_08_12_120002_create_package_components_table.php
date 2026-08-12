<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('component_facility_id')->constrained('facilities')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['package_facility_id', 'component_facility_id'], 'pkg_comp_unique');
            $table->index('package_facility_id');
            $table->index('component_facility_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_components');
    }
};
