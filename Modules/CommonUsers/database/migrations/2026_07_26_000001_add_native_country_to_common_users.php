<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('common_users', function (Blueprint $table) {
            // `country` has always meant the visa destination country (it feeds
            // "Traveling Country" on generated documents). This adds the
            // applicant's own nationality/residency as a distinct field, so the
            // two are never conflated on the form or in reports.
            $table->string('native_country')->default('Sri Lanka')->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('common_users', function (Blueprint $table) {
            $table->dropColumn('native_country');
        });
    }
};
