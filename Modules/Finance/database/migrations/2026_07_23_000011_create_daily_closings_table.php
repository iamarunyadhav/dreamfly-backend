<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->unique();
            $table->bigInteger('opening_balance')->default(0);
            $table->unsignedBigInteger('income_total')->default(0);
            $table->unsignedBigInteger('expense_total')->default(0);
            $table->unsignedBigInteger('cash_total')->default(0);
            $table->unsignedBigInteger('bank_total')->default(0);
            $table->bigInteger('closing_balance')->default(0);
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('variance')->default(0);
            $table->string('status')->default('open'); // open | closed
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('generated_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
