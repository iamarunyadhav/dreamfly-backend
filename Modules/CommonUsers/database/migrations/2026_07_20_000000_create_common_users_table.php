<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('nic')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('visa_type')->nullable();
            $table->unsignedBigInteger('agreement_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status')->default('unpaid'); // unpaid | partially_paid | fully_paid | converted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('common_users');
    }
};
