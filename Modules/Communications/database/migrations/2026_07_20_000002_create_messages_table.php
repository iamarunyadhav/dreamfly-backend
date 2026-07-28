<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('channel'); // whatsapp | email | sms
            $table->string('recipient'); // phone or email address
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
