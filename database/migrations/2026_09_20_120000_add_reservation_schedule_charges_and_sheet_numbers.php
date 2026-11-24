<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('hall_extension_charge', 12, 2)->default(0);
            $table->unsignedBigInteger('guest_sheet_control_number')->nullable()->unique();
        });
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_number');
        });
        DB::table('document_sequences')->insert(['name' => 'guest_information_sheet', 'next_number' => 1]);

        // Dedicated rates leave staff's base, seasonal, early and late rates intact.
        foreach (['diamond-hall' => 5000, 'sapphire-hall' => 2500, 'emerald-hall' => 3000] as $slug => $amount) {
            $id = DB::table('facilities')->where('slug', $slug)->value('id');
            if ($id && ! DB::table('rates')->where('facility_id', $id)->where('name', 'Hall Extension')->exists()) {
                DB::table('rates')->insert([
                    'facility_id' => $id, 'name' => 'Hall Extension', 'type' => 'additional_charge',
                    'amount' => $amount, 'unit' => 'per hour', 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['guest_sheet_control_number']);
            $table->dropColumn(['hall_extension_charge', 'guest_sheet_control_number']);
        });
    }
};
