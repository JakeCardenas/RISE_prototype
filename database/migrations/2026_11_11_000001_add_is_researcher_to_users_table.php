<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opens the capstone evaluation workspace. Set with `php artisan rise:researcher`, never from a form.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_researcher')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_researcher');
        });
    }
};
