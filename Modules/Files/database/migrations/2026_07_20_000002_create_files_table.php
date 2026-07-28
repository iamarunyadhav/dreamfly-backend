<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('original_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
