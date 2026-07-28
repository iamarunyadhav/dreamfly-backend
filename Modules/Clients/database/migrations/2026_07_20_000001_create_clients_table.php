<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('common_user_id')->nullable()->constrained('common_users')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('full_name');
            $table->string('passport_no')->nullable();
            $table->string('nic')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('visa_type')->nullable();
            $table->unsignedBigInteger('agreement_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->foreignId('assigned_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('current_stage')->default('admin_summary'); // admin_summary | application_unit | documentation_unit | supervisor_review | invoice | submission | visa_result | closed
            $table->string('status')->default('active'); // active | on_hold | closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['current_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
