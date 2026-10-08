<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a complete JSON OCR payload to AC/DC comparison bills.
     *
     * IMPORTANT:
     * This migration is ONLY for the separate Comparison AC & DC system.
     * Existing WAPDA Bill Management remains untouched.
     */
    public function up(): void
    {
        Schema::table('comparison_bills', function (Blueprint $table) {
            $table->json('ocr_data')
                ->nullable()
                ->after('ocr_confidence');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('comparison_bills', function (Blueprint $table) {
            $table->dropColumn('ocr_data');
        });
    }
};