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
        Schema::create('violations', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary key
            $table->string('nature_of_violation'); // Nature of the violation
            $table->string('type_of_inspection'); // Type of inspection (e.g., routine, complaint)
            $table->string('violation_receipt_no')->unique(); // Receipt number for violation
            $table->date('violation_date'); // Date the violation occurred
            $table->date('due_date')->nullable(); // Due date for resolution
            $table->enum('status', ['pending', 'resolved', 'in-progress'])->default('pending'); // Status of the violation
            $table->string('violation_status')->nullable(); // Detailed status (optional)
            $table->unsignedBigInteger('inspector_id'); // Foreign key to inspectors
            $table->unsignedBigInteger('business_id'); // Foreign key to businesses
            $table->timestamps(); // Adds created_at and updated_at columns

            // Foreign key constraints
            $table->foreign('inspector_id')
                ->references('id')
                ->on('inspectors')
                ->onDelete('cascade');

            $table->foreign('business_id')
                ->references('id')
                ->on('businesses')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations'); // Drops the table
    }
};
