<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Match the front-desk Guest Information Sheet on both walk-in and online stays.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('guest_place_of_origin')->nullable()->after('guest_phone');
            $table->string('guest_nationality', 100)->nullable()->after('guest_place_of_origin');
            $table->string('guest_company')->nullable()->after('guest_nationality');
            $table->string('guest_valid_id', 100)->nullable()->after('guest_company');
            $table->string('guest_gender', 16)->nullable()->after('guest_valid_id');
            $table->json('other_guest_names')->nullable()->after('guest_count');
            $table->string('guest_signature')->nullable()->after('other_guest_names');
            $table->timestamp('guest_signed_at')->nullable()->after('guest_signature');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'guest_place_of_origin',
                'guest_nationality',
                'guest_company',
                'guest_valid_id',
                'guest_gender',
                'other_guest_names',
                'guest_signature',
                'guest_signed_at',
            ]);
        });
    }
};
