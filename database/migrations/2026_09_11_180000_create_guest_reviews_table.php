<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('stay_label')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('featured_on_home')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'featured_on_home']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_reviews');
    }
};
