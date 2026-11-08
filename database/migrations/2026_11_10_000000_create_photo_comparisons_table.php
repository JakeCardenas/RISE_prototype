<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One AI comparison of two photos of a spot, run only when the resident asks. The result is kept apart from
        // the resident's own notes and is removed with either photo.
        Schema::create('photo_comparisons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('monitoring_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('earlier_record_id')->constrained('photo_records')->cascadeOnDelete();
            $table->foreignUlid('later_record_id')->constrained('photo_records')->cascadeOnDelete();
            $table->string('status', 20);                          // pending, processing, completed, failed
            $table->string('provider', 60);                        // the name residents saw before sending
            $table->string('model_id', 120)->nullable();
            $table->string('model_version', 120)->nullable();
            $table->json('result')->nullable();                    // short, filtered sentences only, never the raw response
            $table->string('error_code', 40)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['earlier_record_id', 'later_record_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_comparisons');
    }
};
