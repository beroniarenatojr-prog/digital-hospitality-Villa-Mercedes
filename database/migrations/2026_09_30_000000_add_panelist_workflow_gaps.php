<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->text('exclusions')->nullable();
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->json('pricing_breakdown')->nullable();
            $table->json('late_checkout_request')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['pricing_breakdown', 'late_checkout_request']));
        Schema::table('facilities', fn (Blueprint $table) => $table->dropColumn('exclusions'));
    }
};
