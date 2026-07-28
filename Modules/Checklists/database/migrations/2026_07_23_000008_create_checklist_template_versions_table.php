<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->string('owner');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('document_required')->default(false);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['checklist_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_template_versions');
    }
};
