<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->unsignedBigInteger('total_service_fee')->default(0);
            $table->unsignedBigInteger('advance_paid')->default(0);
            $table->unsignedBigInteger('application_fee')->default(0);
            $table->unsignedBigInteger('vfs_fee')->default(0);
            $table->string('status')->default('draft'); // draft | sent | paid | partial | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
