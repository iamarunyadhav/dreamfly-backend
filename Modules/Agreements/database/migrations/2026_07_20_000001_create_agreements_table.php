<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->string('client_name');
            $table->string('client_address')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_nic')->nullable();
            $table->string('client_passport_no')->nullable();
            $table->string('client_email')->nullable();
            $table->string('visa_type')->nullable();
            $table->string('country')->nullable();
            $table->unsignedBigInteger('total_fee')->default(0); // LKR, minor-unit-free for a skeleton
            $table->unsignedBigInteger('advance_paid')->default(0);
            $table->string('status')->default('draft'); // draft | sent | signed | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
