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
            $table->char('violation_id', 36)->primary();
            $table->string('nature_of_violation');
            $table->string('type_of_inspection');
            $table->string('violation_receipt_no')->unique();
            $table->date('violation_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->string('violation_status')->nullable();
            $table->char('business_id', 36); // Foreign key column
            $table->timestamps();

            // Ensure the foreign key reference is properly formed
            $table->index('business_id'); // Add index for business_id
            $table->foreign('business_id')
                ->references('business_id')
                ->on('businesses')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the violations table if it exists
        Schema::dropIfExists('violations');
    }
};
