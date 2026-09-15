<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->index(['facility_type_id', 'is_active', 'sort_order', 'id'], 'facilities_public_listing_index');
        });

        Schema::table('gallery_images', function (Blueprint $table) {
            $table->index(['facility_id', 'is_active', 'sort_order', 'id'], 'gallery_images_public_display_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['facility_id', 'status', 'check_in', 'check_out'], 'reservations_availability_index');
            $table->index(['user_id', 'created_at'], 'reservations_customer_history_index');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'inquiries_customer_history_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('created_at', 'payments_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_created_at_index');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex('inquiries_customer_history_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_availability_index');
            $table->dropIndex('reservations_customer_history_index');
        });

        Schema::table('gallery_images', function (Blueprint $table) {
            $table->dropIndex('gallery_images_public_display_index');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex('facilities_public_listing_index');
        });
    }
};
