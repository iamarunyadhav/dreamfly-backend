<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->boolean('is_general')->default(false); // auto-cloned into every client's folder tree
            $table->boolean('is_active')->default(true);
            $table->boolean('public_download')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
