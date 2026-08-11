<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Retained as a no-op so existing installations do not lose shared settings.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op migration.
    }
};
