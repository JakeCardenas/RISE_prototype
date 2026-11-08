<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The old upload form sent the file's last-modified time as "captured_at". That is when the file was last changed
        // (exported, copied, downloaded), not when the photo was taken, so existing values keep their timestamps under an
        // honest name and new uploads no longer send one.
        Schema::table('photo_records', function (Blueprint $table) {
            $table->renameColumn('captured_at', 'file_modified_at');
        });

        Schema::table('photo_records', function (Blueprint $table) {
            // When the photo says it was taken, read from its EXIF details on upload: the camera clock's local date and time,
            // plus its UTC offset when the camera recorded one. Unverified metadata; blank when the file has none.
            $table->dateTime('photo_taken_at')->nullable();
            $table->string('photo_taken_offset', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('photo_records', function (Blueprint $table) {
            $table->dropColumn(['photo_taken_at', 'photo_taken_offset']);
        });

        Schema::table('photo_records', function (Blueprint $table) {
            $table->renameColumn('file_modified_at', 'captured_at');
        });
    }
};
