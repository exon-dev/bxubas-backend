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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id'); // Primary key
            $table->string('type')->default('initial');
            $table->string('title'); // Notification title
            $table->text('content'); // Notification content
            $table->string('status');  // Can be 'pending', 'sent', 'failed'
            $table->text('error_message')->nullable();
            $table->char('violator_id', 36); // Foreign key to business_owners table
            $table->char('violation_id', 36); // Foreign key to violations table
            $table->timestamps(); // Adds created_at and updated_at columns

            // Define foreign key constraints
            $table->foreign('violator_id')
                ->references('business_owner_id')
                ->on('business_owners')
                ->onDelete('cascade');
            $table->foreign('violation_id')
                ->references('violation_id')
                ->on('violations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications'); // Drops the table if it exists
    }
};
