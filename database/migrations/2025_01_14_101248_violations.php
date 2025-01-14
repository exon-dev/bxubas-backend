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
            $table->engine = 'InnoDB'; // Ensure InnoDB engine is used

            $table->char('violation_id', 36)->primary();
            $table->string('type_of_inspection');
            $table->string('violation_receipt_no')->unique();
            $table->date('violation_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->string('violation_status')->nullable();
            $table->char('business_id', 36); // Foreign key column for business_id
            $table->unsignedBigInteger('inspection_id')->nullable(); // Corrected to match the inspection_id type
            $table->timestamps();

            // Foreign key for business_id
            $table->index('business_id');
            $table->foreign('business_id')
                ->references('business_id')
                ->on('businesses')
                ->onDelete('cascade');

            // Foreign key for inspection_id
            $table->index('inspection_id');
            $table->foreign('inspection_id')
                ->references('inspection_id')
                ->on('inspections')
                ->onDelete('set null'); // If the related inspection is deleted, set the inspection_id to null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraints and the violations table
        Schema::table('violations', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropForeign(['inspection_id']);
        });

        Schema::dropIfExists('violations');
    }
};
