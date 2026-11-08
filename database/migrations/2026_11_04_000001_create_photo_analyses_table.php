<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One requested AI analysis of a photo record; a retry adds a new row so every attempt stays traceable
        Schema::create('photo_analyses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('photo_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);                          // pending, processing, completed, failed, not_configured
            $table->string('provider', 40);
            $table->string('model_id', 120)->nullable();           // the model RISE asked for
            $table->string('model_version', 120)->nullable();      // the model the provider reports it ran
            $table->string('provider_request_id', 120)->nullable();
            $table->decimal('confidence_threshold', 4, 3)->nullable();
            $table->json('result')->nullable();                    // normalized regions only, never the raw response
            $table->unsignedInteger('region_count')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['photo_record_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_analyses');
    }
};
