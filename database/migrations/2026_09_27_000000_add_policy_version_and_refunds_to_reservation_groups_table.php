<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_groups', function (Blueprint $table) {
            $table->string('policy_version')->nullable()->after('policies_accepted_at');
            $table->boolean('requires_quote')->default(false)->after('status');
            $table->timestamp('quote_requested_at')->nullable()->after('requires_quote');
            $table->timestamp('quoted_at')->nullable()->after('quote_requested_at');
            $table->foreignId('quoted_by')->nullable()->after('quoted_at')->constrained('users')->nullOnDelete();

            $table->string('refund_status')->nullable()->after('rejection_reason');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_status');
            $table->text('refund_notes')->nullable()->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_notes');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_id')->nullable()->change();
            $table->foreignId('reservation_group_id')->nullable()->after('reservation_id')->constrained('reservation_groups')->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('policy_version')->nullable()->after('policies_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('policy_version');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_group_id');
        });

        Schema::table('reservation_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quoted_by');
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn([
                'policy_version',
                'requires_quote',
                'quote_requested_at',
                'quoted_at',
                'refund_status',
                'refund_amount',
                'refund_notes',
                'refunded_at',
            ]);
        });
    }
};
