<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['night', 'event', 'hour', 'person', 'day', 'session', 'package'] as $unit) {
            DB::table('rates')->where('unit', 'per_'.$unit)->update(['unit' => 'per '.$unit]);
        }
    }

    public function down(): void
    {
        // The old and new spellings had the same meaning; no pricing snapshots change.
    }
};
