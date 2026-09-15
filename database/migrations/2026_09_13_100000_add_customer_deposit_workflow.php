<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank')->nullable();
            $table->string('qr_path');
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('deposit_required', 10, 2)->nullable();
            $table->timestamp('deposit_due_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('review_invited_at')->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_destination_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('destination_snapshot')->nullable();
            $table->string('source')->default('legacy');
            $table->timestamp('collected_at')->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->string('proof_hash', 64)->nullable()->index();
        });
        Schema::table('guest_reviews', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->boolean('is_published')->default(false)->change();
            // Null preserves the visibility of pre-existing published reviews.
            $table->boolean('public_consent')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            // New submissions use this key; legacy records are not deduplicated or deleted.
            $table->foreignId('reviewed_reservation_id')->nullable()->unique()->constrained('reservations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guest_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_reservation_id');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['public_consent', 'moderated_at', 'moderation_reason']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_destination_id');
            $table->dropColumn(['destination_snapshot', 'source', 'collected_at', 'reconciliation_note', 'proof_hash']);
        });
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['deposit_required', 'deposit_due_at', 'expired_at', 'review_invited_at']));
        Schema::dropIfExists('payment_destinations');
    }
};
