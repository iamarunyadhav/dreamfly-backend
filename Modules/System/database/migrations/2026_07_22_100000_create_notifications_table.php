<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('documentation_task_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('unread')->index();
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
