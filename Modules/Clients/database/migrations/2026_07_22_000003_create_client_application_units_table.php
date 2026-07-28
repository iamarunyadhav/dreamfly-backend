<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_application_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->json('form_data')->nullable();
            $table->json('applicant_checklist')->nullable();
            $table->json('inviter_checklist')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generated_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_application_units');
    }
};

