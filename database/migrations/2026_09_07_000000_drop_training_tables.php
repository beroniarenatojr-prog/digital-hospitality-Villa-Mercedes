<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('training_applications');
        Schema::dropIfExists('training_batch_facility');
        Schema::dropIfExists('training_batches');
    }

    public function down(): void
    {
        // Training was removed from the product. Tables are not restored.
    }
};
