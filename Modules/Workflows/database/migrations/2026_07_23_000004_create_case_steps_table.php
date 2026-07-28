<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('workflow_template_id')->nullable()->constrained('workflow_templates')->nullOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->string('owner_role')->nullable();
            // pending | in_progress | on_hold | waiting | completed | skipped
            $table->string('status')->default('pending');
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('requires_checklist')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hold_reason')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'key']);
            $table->index(['client_id', 'order']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_steps');
    }
};
