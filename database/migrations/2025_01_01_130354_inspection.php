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
            $table->id('inspection_id'); // Primary key
            $table->date('inspection_date'); // Date of inspection
            $table->string('type_of_inspection'); // Type of inspection
            $table->boolean('with_violations'); // Whether the inspection had violations
            $table->char('business_id', 36); // Foreign key to businesses table
            $table->char('inspector_id', 36); // Foreign key to inspectors table
            $table->timestamps(); // Adds created_at and updated_at columns

            // Define foreign key constraints
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
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections'); // Drops the table if it exists
    }
};
