<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_role')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('hold_reason')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('escalation_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['assigned_user_id', 'due_at']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_tasks');
    }
};
