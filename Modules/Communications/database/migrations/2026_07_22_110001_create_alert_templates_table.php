<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('name');
            $table->string('trigger')->index();
            $table->json('conditions')->nullable();
            $table->json('recipient_rules')->nullable();
            $table->json('channels');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('repeat_rule')->nullable();
            $table->string('escalation_rule')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trigger', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_templates');
    }
};
