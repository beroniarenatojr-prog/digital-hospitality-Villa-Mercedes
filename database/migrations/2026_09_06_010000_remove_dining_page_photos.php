<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $photos = DB::table('page_photos')->where('collection', 'dining')->get();

        foreach ($photos as $photo) {
            if (is_string($photo->image_path) && $photo->image_path !== '') {
                Storage::disk('public')->delete($photo->image_path);
            }
        }

        DB::table('page_photos')->where('collection', 'dining')->delete();
    }

    public function down(): void
    {
        // Dining photos are not restored. Re-upload them if the collection returns.
    }
};
