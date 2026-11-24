<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('facilities')
            ->whereIn('facility_type_id', DB::table('facility_types')->select('id')->where('slug', 'cottage'))
            ->update(['max_capacity' => 50]);
    }

    public function down(): void
    {
        // Prior cottage limits varied by installation and cannot be restored.
    }
};
