<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A private JPEG copy used for viewing and analysis when the original is HEIC or carries location data.
        // The original file stays in image_path, unchanged.
        Schema::table('photo_records', function (Blueprint $table) {
            $table->string('display_path')->nullable();
            $table->string('display_mime', 64)->nullable();
            $table->unsignedInteger('display_width')->nullable();
            $table->unsignedInteger('display_height')->nullable();
            $table->string('display_reason', 32)->nullable();  // converted_from_heic or location_removed
        });
    }

    public function down(): void
    {
        Schema::table('photo_records', function (Blueprint $table) {
            $table->dropColumn(['display_path', 'display_mime', 'display_width', 'display_height', 'display_reason']);
        });
    }
};
