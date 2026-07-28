<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner')->nullable(); // applicant | inviter | internal | null (any)
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'owner']);
        });

        $now = now();
        $defaults = [
            ['name' => 'client_documents', 'owner' => 'applicant'],
            ['name' => 'inviter', 'owner' => 'inviter'],
            ['name' => 'application_processing', 'owner' => 'internal'],
            ['name' => 'documentation_unit', 'owner' => 'internal'],
            ['name' => 'submission', 'owner' => 'internal'],
            ['name' => 'final_review', 'owner' => 'internal'],
        ];

        DB::table('checklist_categories')->insert(array_map(fn (array $d, int $i) => [
            'name' => $d['name'],
            'owner' => $d['owner'],
            'order' => $i,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $defaults, array_keys($defaults)));
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_categories');
    }
};
