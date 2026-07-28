<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared, growable country list used by both the native-country and the
        // visa-destination-country dropdowns on Common User / Client forms. A
        // custom value typed against "Others" and marked General gets inserted
        // here so it appears for every future registration; marked Specific, it
        // is used as-is on that one record only and never reaches this table.
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $names = [
            // Native default first.
            'Sri Lanka',
            // Common visa-destination countries for a Sri Lankan consultancy.
            'United Kingdom', 'United States', 'Canada', 'Australia', 'New Zealand',
            'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Belgium', 'Switzerland',
            'Austria', 'Sweden', 'Norway', 'Denmark', 'Finland', 'Ireland', 'Portugal',
            'Greece', 'Poland', 'Czech Republic', 'Hungary',
            'United Arab Emirates', 'Qatar', 'Saudi Arabia', 'Kuwait', 'Bahrain', 'Oman', 'Jordan', 'Israel',
            'Singapore', 'Malaysia', 'Thailand', 'Japan', 'South Korea', 'China', 'Hong Kong', 'Taiwan',
            'India', 'Pakistan', 'Bangladesh', 'Nepal', 'Maldives',
            'South Africa', 'Kenya', 'Nigeria',
            'Brazil', 'Mexico',
            'Russia', 'Turkey', 'Cyprus', 'Malta',
            'Philippines', 'Indonesia', 'Vietnam',
        ];

        DB::table('countries')->insert(array_map(fn (string $name) => [
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names));
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
