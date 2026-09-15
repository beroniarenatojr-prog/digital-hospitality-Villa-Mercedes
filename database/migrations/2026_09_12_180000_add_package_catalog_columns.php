<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('package_family')->nullable()->after('sort_order');
            $table->string('stay_category')->nullable()->after('package_family');
            $table->time('stay_start_time')->nullable()->after('stay_category');
            $table->time('stay_end_time')->nullable()->after('stay_start_time');
            $table->boolean('stay_spans_midnight')->default(false)->after('stay_end_time');
            $table->boolean('is_listed')->default(true)->after('stay_spans_midnight');

            $table->index(['package_family', 'stay_category']);
            $table->index('is_listed');
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedInteger('max_occupancy')->nullable()->after('unit');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('pax_tier')->nullable()->after('guest_count');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex(['package_family', 'stay_category']);
            $table->dropIndex(['is_listed']);
            $table->dropColumn([
                'package_family',
                'stay_category',
                'stay_start_time',
                'stay_end_time',
                'stay_spans_midnight',
                'is_listed',
            ]);
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn('max_occupancy');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('pax_tier');
        });
    }
};
