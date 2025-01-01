<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id('inspection_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('inspector_id');
            $table->date('inspection_date');
            $table->string('type_of_inspection');
            $table->boolean('with_violations');

            $table->foreign('business_id')
                ->references('business_id')
                ->on('businesses')
                ->onDelete('cascade');
            $table->foreign('inspector_id')
                ->references('inspector_id')
                ->on('inspectors')
                ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
