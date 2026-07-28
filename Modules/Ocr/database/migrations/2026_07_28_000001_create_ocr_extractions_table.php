<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('provider')->default('google_vision');
            $table->json('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_extractions');
    }
};
