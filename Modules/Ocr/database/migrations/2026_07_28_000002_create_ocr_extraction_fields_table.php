<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_extraction_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ocr_extraction_id')->constrained('ocr_extractions')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label');
            $table->text('value')->nullable();
            $table->float('confidence')->nullable();
            $table->boolean('is_user_edited')->default(false);
            $table->timestamps();

            $table->index('ocr_extraction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_extraction_fields');
    }
};
