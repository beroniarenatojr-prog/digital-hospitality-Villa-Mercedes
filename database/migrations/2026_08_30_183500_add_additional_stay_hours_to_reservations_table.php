<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedTinyInteger('early_check_in_hours')->default(0)->after('guest_count');
            $table->unsignedTinyInteger('late_check_out_hours')->default(0)->after('early_check_in_hours');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['early_check_in_hours', 'late_check_out_hours']);
        });
    }
};
