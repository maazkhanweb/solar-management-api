<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the dedicated AC/DC Comparison bill records table.
     *
     * IMPORTANT:
     * This table is completely separate from the existing `bills` table.
     * Existing WAPDA Bill Management OCR/data remains untouched.
     */
    public function up(): void
    {
        Schema::create('comparison_bills', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Owner / User
            |--------------------------------------------------------------------------
            | The logged-in user who uploaded the bill.
            | This lets Admin see which Gmail/account owns each record.
            */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Uploaded File
            |--------------------------------------------------------------------------
            */
            $table->string('bill_file')->nullable();
            $table->string('original_file_name')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Consumer Information - OCR
            |--------------------------------------------------------------------------
            */
            $table->string('consumer_name')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('consumer_id')->nullable();
            $table->string('area_name')->nullable();
            $table->string('tariff_category')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Bill Information - OCR
            |--------------------------------------------------------------------------
            */
            $table->unsignedTinyInteger('bill_month')->nullable();
            $table->unsignedSmallInteger('bill_year')->nullable();
            $table->string('issue_date')->nullable();
            $table->string('due_date')->nullable();

            $table->decimal('payable_before_due', 14, 2)->nullable();
            $table->decimal('payable_after_due', 14, 2)->nullable();
            $table->decimal('current_bill', 14, 2)->nullable();
            $table->decimal('arrears', 14, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Meter Readings - OCR
            |--------------------------------------------------------------------------
            */
            $table->decimal('previous_reading', 14, 2)->nullable();
            $table->decimal('present_reading', 14, 2)->nullable();
            $table->decimal('units_consumed', 14, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | OCR Information
            |--------------------------------------------------------------------------
            */
            $table->boolean('ocr_status')->default(false);
            $table->decimal('ocr_confidence', 5, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Home Analysis
            |--------------------------------------------------------------------------
            | Stores the exact Home Analysis form input and calculated result
            | alongside the uploaded bill. This is separate from WAPDA Bill data.
            */
            $table->json('home_analysis_data')->nullable();
            $table->json('home_analysis_result')->nullable();
            $table->timestamp('home_analysis_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Admin Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            | Reference numbers repeat across monthly bills, so it must NOT be unique.
            */
            $table->index('user_id');
            $table->index('reference_number');
            $table->index('consumer_id');
            $table->index('area_name');
            $table->index(['bill_year', 'bill_month']);
            $table->index('ocr_status');
            $table->index('home_analysis_at');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('comparison_bills');
    }
};
