<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money the consultancy owes to someone else (VFS/embassy fees, agent
        // commissions, rent, utilities, staff advances) - the mirror of
        // receivables, which tracks money owed TO the consultancy. Settling one
        // posts a real expense entry to the ledger, same as a payment posts income.
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->string('payee');
            // vfs_fee | embassy_fee | agent_commission | rent | utility | staff_advance | other
            $table->string('category');
            $table->unsignedBigInteger('amount');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            // pending | paid | cancelled
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->date('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};
