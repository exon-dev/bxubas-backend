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
        Schema::create('inspectors', function (Blueprint $table) {
            // Define the primary key (UUID)
            $table->char('inspector_id', 36)->primary();

            // Foreign key referencing the admins table
            $table->char('admin_id', 36);

            // Additional fields
            $table->string('email')->unique(); // Inspector's unique email
            $table->string('first_name'); // Inspector's first name
            $table->string('last_name'); // Inspector's last name
            $table->string('password'); // Inspector's password (hashed)

            // Timestamps for created_at and updated_at
            $table->timestamps();

            // Define the foreign key constraint
            $table->foreign('admin_id')
                ->references('admin_id') // Referencing admin_id in the admins table
                ->on('admins') // Table to reference
                ->onDelete('cascade'); // Cascade delete to remove related inspectors
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the inspectors table if it exists
        Schema::dropIfExists('inspectors');
    }
};
