<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->string('owner_role')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
