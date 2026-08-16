<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra one-off charges (e.g. "Police report document - 12,000") layered on
     * top of a lead/client's agreement amount. Owned by exactly one of
     * common_user_id/client_id, mirroring the same dual-nullable-FK ownership
     * pattern already used on `files`.
     */
    public function up(): void
    {
        Schema::create('additional_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('common_user_id')->nullable()->constrained('common_users')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['common_user_id']);
            $table->index(['client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_charges');
    }
};
