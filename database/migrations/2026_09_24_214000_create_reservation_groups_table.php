<?php

use App\Enums\ReservationGroupStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('group_code')->unique();
            $table->string('source')->default(ReservationSource::Online->value); // online, walk_in
            $table->string('status')->default(ReservationGroupStatus::Pending->value); // pending, approved, rejected, cancelled, completed

            // Guest identity summary (denormalized or captured directly)
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->string('guest_place_of_origin')->nullable();
            $table->string('guest_nationality')->nullable();
            $table->string('guest_company')->nullable();
            $table->string('guest_valid_id')->nullable();
            $table->string('guest_gender')->nullable();

            // Financial & approval details
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('deposit_required', 10, 2)->nullable();
            $table->dateTime('deposit_due_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('special_requests')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('policies_accepted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('source');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('reservation_group_id')->nullable()->after('user_id')->constrained('reservation_groups')->nullOnDelete();
            $table->index('reservation_group_id');
        });

        // Backfill existing reservations into one-line groups for data integrity and backwards compatibility
        DB::table('reservations')->orderBy('id')->chunk(100, function ($reservations) {
            foreach ($reservations as $res) {
                // Determine group status based on reservation status
                $groupStatus = match ($res->status) {
                    ReservationStatus::Approved->value => ReservationGroupStatus::Approved->value,
                    ReservationStatus::Rejected->value => ReservationGroupStatus::Rejected->value,
                    ReservationStatus::Cancelled->value, ReservationStatus::Expired->value => ReservationGroupStatus::Cancelled->value,
                    ReservationStatus::Completed->value => ReservationGroupStatus::Completed->value,
                    default => ReservationGroupStatus::Pending->value,
                };

                $groupId = DB::table('reservation_groups')->insertGetId([
                    'user_id' => $res->user_id,
                    'group_code' => 'GRP-' . $res->reservation_code,
                    'source' => $res->reservation_source ?? ReservationSource::Online->value,
                    'status' => $groupStatus,
                    'guest_name' => $res->guest_name ?? 'Guest',
                    'guest_email' => $res->guest_email ?? 'guest@example.com',
                    'guest_phone' => $res->guest_phone ?? '',
                    'guest_place_of_origin' => $res->guest_place_of_origin,
                    'guest_nationality' => $res->guest_nationality,
                    'guest_company' => $res->guest_company,
                    'guest_valid_id' => $res->guest_valid_id,
                    'guest_gender' => $res->guest_gender,
                    'total_amount' => $res->total_price ?? 0.00,
                    'deposit_required' => $res->deposit_required,
                    'deposit_due_at' => $res->deposit_due_at,
                    'approved_at' => $res->approved_at,
                    'rejected_at' => $res->rejected_at,
                    'rejection_reason' => $res->rejection_reason,
                    'cancelled_at' => $res->cancelled_at,
                    'cancellation_reason' => $res->cancellation_reason,
                    'completed_at' => $res->completed_at,
                    'special_requests' => $res->special_requests,
                    'admin_notes' => $res->admin_notes,
                    'policies_accepted_at' => $res->policies_accepted_at,
                    'created_at' => $res->created_at ?? now(),
                    'updated_at' => $res->updated_at ?? now(),
                ]);

                DB::table('reservations')
                    ->where('id', $res->id)
                    ->update(['reservation_group_id' => $groupId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['reservation_group_id']);
            $table->dropColumn('reservation_group_id');
        });

        Schema::dropIfExists('reservation_groups');
    }
};
