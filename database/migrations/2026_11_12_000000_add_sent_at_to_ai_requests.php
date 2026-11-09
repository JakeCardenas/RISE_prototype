<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the photos had been fully sent to the provider, so a waiting page can tell "sending" from "waiting for
        // the result"
        foreach (['photo_analyses', 'photo_comparisons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable()->after('started_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['photo_analyses', 'photo_comparisons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('sent_at');
            });
        }
    }
};
