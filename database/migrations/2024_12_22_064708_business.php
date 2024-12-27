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
            $table->bigIncrements('id'); // Primary key
            $table->string('business_id')->unique(); // Unique identifier for the business
            $table->string('business_name'); // Name of the business
            $table->string('image_url')->nullable(); // Optional URL for business image
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active'); // Status of the business
            $table->unsignedBigInteger('owner_id'); // Foreign key to the business_owners table
            $table->timestamps(); // Adds created_at and updated_at columns

            // Foreign key constraint
            $table->foreign('owner_id')
                ->references('id')
                ->on('business_owners')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
