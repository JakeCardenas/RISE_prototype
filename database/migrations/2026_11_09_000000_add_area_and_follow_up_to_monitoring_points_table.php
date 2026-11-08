<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Both optional, so existing points keep working unchanged: the room or part of the home a point is in
        // ("Bedroom", "Exterior" or the resident's own name), and the date the resident plans its next photo.
        Schema::table('monitoring_points', function (Blueprint $table) {
            $table->string('area', 40)->nullable()->after('label');
            $table->date('follow_up_on')->nullable()->after('surface_description');

            $table->index(['user_id', 'follow_up_on']);
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_points', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'follow_up_on']);
            $table->dropColumn(['area', 'follow_up_on']);
        });
    }
};
