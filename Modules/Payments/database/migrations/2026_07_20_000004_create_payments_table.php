<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained('agreements')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method')->nullable(); // cash | bank | online | ...
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
