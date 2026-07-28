<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A trigger fires by queueing one row per matching enabled template.
        // A scheduled command then sends everything that is due. Queueing rather
        // than sending inline is what makes `delay_minutes` and repeats work, and
        // it leaves an audit trail of what fired and what happened.
        Schema::create('alert_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_template_id')->constrained('alert_templates')->cascadeOnDelete();
            $table->string('trigger')->index();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            // Stops the same event queueing the same alert twice (e.g. a
            // five-minute scheduler re-running over the same overdue task).
            $table->string('dedupe_key')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('due_at')->index();
            // pending | sent | failed | skipped
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('recipients_notified')->default(0);
            $table->timestamps();

            $table->unique(['alert_template_id', 'dedupe_key']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_dispatches');
    }
};
