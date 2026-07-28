<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_responsibility_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            // Operator-editable notes appended below the fixed notice wording
            // (the fixed wording itself lives in the Blade template, same as the
            // agreement/invoice documents - it is not stored per-row).
            $table->text('content')->nullable();
            // draft | generated | shared | acknowledged
            $table->string('status')->default('draft');
            $table->foreignId('generated_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('shared_at')->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            // whatsapp_reply | email_reply | signed_copy | verbal | other
            $table->string('acknowledgement_method')->nullable();
            $table->text('acknowledgement_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['acknowledged']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_responsibility_notices');
    }
};
