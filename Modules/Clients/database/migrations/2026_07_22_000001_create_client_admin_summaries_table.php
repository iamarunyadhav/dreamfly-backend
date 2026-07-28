<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_admin_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('client_share_notes')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('application_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('deadline_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['deadline_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_admin_summaries');
    }
};

