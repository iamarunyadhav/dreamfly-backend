<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The final-document handover record for stage 19 (Case Closure): which
        // original documents were returned to the client, whether the case file
        // has been archived, and who signed off - kept separate from the shared
        // Application Unit checklist pool so it can never block an earlier step.
        Schema::create('client_case_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->json('handover_checklist')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_case_closures');
    }
};
