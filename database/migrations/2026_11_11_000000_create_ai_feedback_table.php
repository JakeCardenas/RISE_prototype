<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A resident's own rating of one AI result: useful, incorrect or uncertain, with an optional note. Kept apart
        // from the model's wording and from photo notes, and removed with the result it rates.
        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('photo_analysis_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('photo_comparison_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rating', 20);                          // useful, incorrect, uncertain
            $table->string('note', 500)->nullable();               // the resident's own words
            $table->timestamps();

            $table->unique(['user_id', 'photo_analysis_id']);
            $table->unique(['user_id', 'photo_comparison_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
