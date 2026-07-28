<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->date('submitted_at')->nullable();
            // Embassy / high commission / VFS lodgement reference given to the client.
            $table->string('lodgement_reference')->nullable();
            $table->string('tracking_reference')->nullable();
            // Where it was lodged (VFS centre, embassy, online portal).
            $table->string('submitted_to')->nullable();
            $table->string('submission_method')->nullable(); // vfs | embassy | online | courier | other
            $table->dateTime('appointment_at')->nullable();
            $table->string('appointment_location')->nullable();
            $table->date('biometrics_at')->nullable();
            $table->date('expected_decision_at')->nullable();
            $table->foreignId('receipt_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['submitted_at']);
            $table->index(['expected_decision_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_submissions');
    }
};
