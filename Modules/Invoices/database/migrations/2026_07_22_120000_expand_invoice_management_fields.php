<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('issue_date')->nullable()->after('reference_no');
            $table->date('due_date')->nullable()->after('issue_date');
            $table->text('notes')->nullable()->after('vfs_fee');
            $table->foreignId('generated_file_id')->nullable()->after('status')->constrained('files')->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('label', 'description');
            $table->unsignedInteger('quantity')->default(1)->after('description');
            $table->unsignedBigInteger('unit_price')->default(0)->after('quantity');
            $table->string('category')->nullable()->after('amount');
            $table->unsignedBigInteger('tax')->default(0)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('description', 'label');
            $table->dropColumn(['quantity', 'unit_price', 'category', 'tax']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_file_id');
            $table->dropColumn(['issue_date', 'due_date', 'notes']);
        });
    }
};
