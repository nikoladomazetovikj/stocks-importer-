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
        Schema::table('tbl_product_data', function (Blueprint $table) {
            $table->unsignedInteger('intStock')->after('strProductCode');
            $table->decimal('gbpCost', 10, 2)->after('intStock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_product_data', function (Blueprint $table) {
            $table->dropColumn(['intStock', 'gbpCost']);
        });
    }
};
