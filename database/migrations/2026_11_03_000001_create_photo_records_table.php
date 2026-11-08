<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One dated photo of a monitoring point; the image file lives on a private disk
        Schema::create('photo_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('monitoring_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('image_disk', 32);
            $table->string('image_path');
            $table->string('image_mime', 64);
            $table->unsignedBigInteger('image_size');
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('captured_at')->nullable();  // reported by the resident's device, not verified
            $table->timestamp('submitted_at');              // set by the server when the photo is saved
            $table->timestamps();

            $table->index(['monitoring_point_id', 'submitted_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_records');
    }
};
