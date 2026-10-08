<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the dedicated AC/DC comparison bill record.
     *
     * Existing WAPDA bill tables are NOT changed.
     *
     * The JSON `ocr_data` column keeps the complete normalized OCR payload,
     * so useful bill fields that differ between PESCO/WAPDA/other utilities
     * are not lost just because they do not have a dedicated SQL column.
     */
    public function up(): void
    {
        Schema::table('comparison_bills', function (Blueprint $table) {
            $table->string('bill_address')->nullable()->after('area_name');
            $table->string('status')->nullable()->after('current_bill');

            $table->string('meter_number')->nullable()->after('consumer_id');
            $table->string('bill_type')->nullable()->after('tariff_category');
            $table->string('connection_date')->nullable()->after('bill_year');
            $table->string('reading_date')->nullable()->after('issue_date');

            $table->decimal('sanctioned_load', 14, 2)->nullable()->after('units_consumed');
            $table->decimal('connected_load', 14, 2)->nullable()->after('sanctioned_load');

            /*
             * Complete normalized OCR payload.
             * This preserves additional visible bill information without
             * changing the database schema for every utility-specific field.
             */
            $table->json('ocr_data')->nullable()->after('ocr_confidence');

            $table->index('meter_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('comparison_bills', function (Blueprint $table) {
            $table->dropIndex('comparison_bills_meter_number_index');
            $table->dropIndex('comparison_bills_status_index');

            $table->dropColumn([
                'bill_address',
                'status',
                'meter_number',
                'bill_type',
                'connection_date',
                'reading_date',
                'sanctioned_load',
                'connected_load',
                'ocr_data',
            ]);
        });
    }
};
