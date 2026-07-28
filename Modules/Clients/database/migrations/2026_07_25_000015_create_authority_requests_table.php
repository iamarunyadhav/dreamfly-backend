<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Who asked - embassy, high commission, VFS, or another authority.
            $table->string('authority');
            // additional_documents | interview | medical | biometrics | clarification | other
            $table->string('request_type')->default('additional_documents');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('received_at');
            $table->date('due_at')->nullable();
            // pending | in_progress | responded | overdue | closed | cancelled
            $table->string('status')->default('pending');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('responded_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->foreignId('response_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_requests');
    }
};
