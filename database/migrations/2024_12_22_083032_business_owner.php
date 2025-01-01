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
        Schema::create('business_owners', function (Blueprint $table) {
            $table->char('business_owner_id', 36)->primary(); // Primary key (UUID format)
            $table->string('email')->unique(); // Unique email for the owner
            $table->string('first_name'); // First name
            $table->string('last_name'); // Last name
            $table->string('phone_number'); // Phone number
            $table->timestamps(); // Adds created_at and updated_at columns

            // Optional: Add indexes for faster queries on commonly searched columns
            $table->index('email'); // Index for email
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_owners'); // Drops the table if it exists
    }
};
