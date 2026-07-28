<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // visit_visa | student_visa | other
            $table->text('description')->nullable();
            $table->foreignId('workflow_template_id')->nullable()->constrained('workflow_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });

        Schema::create('service_checklist_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('order')->default(0);

            $table->unique(['service_id', 'checklist_template_id'], 'service_checklist_unique');
        });

        Schema::create('service_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();

            $table->unique(['service_id', 'form_id']);
        });

        Schema::create('service_message_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('message_template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->string('purpose')->nullable(); // welcome | bank_instruction | notice | ...

            $table->unique(['service_id', 'message_template_id'], 'service_message_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_message_template');
        Schema::dropIfExists('service_form');
        Schema::dropIfExists('service_checklist_template');
        Schema::dropIfExists('services');
    }
};
