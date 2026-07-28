<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('amount'); // cash | bank | online | other
            $table->string('source')->default('manual')->after('payment_method'); // manual | payment | adjustment
            $table->foreignId('payment_id')->nullable()->after('source')->constrained('payments')->nullOnDelete();
            $table->text('reason')->nullable()->after('description'); // required for adjustments
            $table->foreignId('adjusts_entry_id')->nullable()->after('reason')->constrained('ledger_entries')->nullOnDelete();
            $table->boolean('is_locked')->default(false)->after('adjusts_entry_id');
            $table->unsignedBigInteger('daily_closing_id')->nullable()->after('is_locked');

            $table->index(['source']);
            $table->index(['payment_method']);
            $table->index(['daily_closing_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('adjusts_entry_id');
            $table->dropColumn(['payment_method', 'source', 'reason', 'is_locked', 'daily_closing_id']);
        });
    }
};
