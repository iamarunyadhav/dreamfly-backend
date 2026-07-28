<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('application_unit_id')->nullable()->constrained('client_application_units')->cascadeOnDelete();
            $table->string('owner'); // applicant | inviter | internal
            $table->unsignedInteger('source_index')->default(0);
            $table->string('title');
            $table->string('status')->default('missing');
            $table->boolean('is_required')->default(true);
            $table->boolean('document_required')->default(true);
            $table->foreignId('linked_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['application_unit_id', 'owner', 'source_index'], 'case_checklist_source_unique');
            $table->index(['client_id', 'owner', 'status']);
            $table->index(['linked_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_checklist_items');
    }
};
