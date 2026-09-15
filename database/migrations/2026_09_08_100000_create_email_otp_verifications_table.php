<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('send_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_verifications');
    }
};
