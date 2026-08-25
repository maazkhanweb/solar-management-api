<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Damage Reason
            |--------------------------------------------------------------------------
            |
            | Stores the reason why an inventory item was marked as damaged.
            |
            */

            $table->text('damage_reason')
                ->nullable()
                ->after('damaged_quantity');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {

            $table->dropColumn('damage_reason');

        });
    }
};