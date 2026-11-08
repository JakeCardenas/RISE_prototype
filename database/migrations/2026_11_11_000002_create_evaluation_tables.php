<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The capstone evaluation workspace: research images with human labels, kept apart from residents' photos and
        // records. A researcher adds each image with the authorization for using it; nothing is copied from residents.
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 20)->unique();                  // EV-0001: the only identifier in exports
            $table->string('image_disk', 40);
            $table->string('image_path');                          // a re-encoded copy without embedded details
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('authorization', 40);                   // the basis the researcher stated when adding it
            $table->string('authorization_note', 300)->nullable();
            $table->string('label', 20)->nullable();               // crack_like, not_crack_like, uncertain
            $table->json('regions')->nullable();                   // boxes drawn by the labeller, as fractions
            $table->string('blur', 20)->nullable();
            $table->string('lighting', 20)->nullable();
            $table->string('surface', 20)->nullable();
            $table->string('split', 20)->default('held_out');      // held_out or development
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('labelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('labelled_at')->nullable();
            $table->timestamps();
        });

        // One provider and model run over the labelled held-out images
        Schema::create('evaluation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider', 40);
            $table->string('model_id', 120)->nullable();
            $table->unsignedInteger('item_count');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One image's result in a run, with the human label and conditions as they were when the run started
        Schema::create('evaluation_predictions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('evaluation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('evaluation_item_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');                              // label, regions, blur, lighting, surface
            $table->string('status', 20);                          // pending, processing, completed, failed
            $table->string('finding', 20)->nullable();
            $table->json('regions')->nullable();                   // boxes, only from providers that return locations
            $table->string('model_version', 120)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_run_id', 'evaluation_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_predictions');
        Schema::dropIfExists('evaluation_runs');
        Schema::dropIfExists('evaluation_items');
    }
};
