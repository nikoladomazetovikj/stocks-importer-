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
        Schema::create('tblProductData', function (Blueprint $table) {
            $table->id('intProductDataId');
            $table->string('strProductName', 50);
            $table->string('strProductDesc', 255);
            $table->string('strProductCode', 10)->unique();
            $table->dateTime('dtmAdded')->nullable();
            $table->dateTime('dtmDiscontinued')->nullable();
            $table->timestamp('stmTimestamp')
                ->useCurrent()
                ->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblProductData');
    }
};
