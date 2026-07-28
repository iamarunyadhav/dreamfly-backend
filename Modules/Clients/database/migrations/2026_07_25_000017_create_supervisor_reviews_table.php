<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per review round. Sending a case back and having it come
        // round again opens a new round, so the sign-off history is preserved
        // rather than overwritten.
        Schema::create('supervisor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedInteger('round')->default(1);
            // pending | approved | sent_back
            $table->string('status')->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            // Which stage the case was pushed back to, and who has to fix it.
            $table->string('sent_back_to_stage')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'round']);
            $table->index(['status']);
        });

        Schema::create('supervisor_review_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_review_id')->constrained('supervisor_reviews')->cascadeOnDelete();
            // Denormalised so the whole thread for a client can be read without
            // joining every round.
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_review_comments');
        Schema::dropIfExists('supervisor_reviews');
    }
};
