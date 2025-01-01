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
        Schema::create('businesses', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // Primary and other columns
            $table->char('business_id', 36)->primary();
            $table->string('business_permit')->unique(); // Unique permit identifier
            $table->string('business_name'); // Name of the business
            $table->string('image_url')->nullable(); // Optional image URL
            $table->string('status'); // Status of the business (active, inactive, etc.)
            $table->char('owner_id', 36); // Foreign key column for the business owner
            $table->timestamps(); // Adds created_at and updated_at

            // Define the foreign key constraint
            $table->foreign('owner_id')
                ->references('business_owner_id')
                ->on('business_owners')
                ->onDelete('cascade'); // Cascade delete when the business owner is deleted
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the businesses table if it exists
        Schema::dropIfExists('businesses');
    }
};
