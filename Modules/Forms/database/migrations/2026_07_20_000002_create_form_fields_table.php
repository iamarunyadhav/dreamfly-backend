<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('type'); // text | textarea | number | date | select | checkbox | file | ...
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['form_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
