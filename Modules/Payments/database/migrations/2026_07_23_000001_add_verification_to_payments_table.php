<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // pending | verified | rejected - a quality gate separate from the
            // financial balance, which continues to count every recorded payment.
            $table->string('status')->default('pending')->after('reference');
            $table->foreignId('receipt_file_id')->nullable()->after('status')->constrained('files')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('receipt_file_id');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable()->after('verified_by');
            $table->boolean('is_overpayment')->default(false)->after('verification_notes');

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_file_id');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['status', 'verified_at', 'verification_notes', 'is_overpayment']);
        });
    }
};
