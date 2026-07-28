<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // income | expense
            $table->string('category'); // service_fee | visa_fee | rent | salary | utility | other
            $table->unsignedBigInteger('amount');
            $table->text('description')->nullable();
            $table->date('entry_date');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type']);
            $table->index(['category']);
            $table->index(['entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
