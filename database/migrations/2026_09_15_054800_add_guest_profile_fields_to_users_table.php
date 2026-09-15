<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('address')->nullable()->after('date_of_birth');
            $table->string('nationality', 100)->nullable()->after('address');
            $table->string('valid_id_type', 32)->nullable()->after('nationality');
            $table->string('company')->nullable()->after('valid_id_type');
            $table->timestamp('terms_accepted_at')->nullable()->after('profile_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'date_of_birth',
                'address',
                'nationality',
                'valid_id_type',
                'company',
                'terms_accepted_at',
            ]);
        });
    }
};
