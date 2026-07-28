<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category'); // applicant | inviter | staff | client_documents | application_processing | documentation_unit | submission | final_review
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('document_required')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_templates');
    }
};
