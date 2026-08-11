<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lark_open_id')->nullable()->unique()->after('remember_token');
            $table->dropColumn(['provider_name', 'provider_id', 'provider_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('remember_token');
            $table->string('provider_id')->nullable()->after('provider_name');
            $table->text('provider_token')->nullable()->after('provider_id');
            $table->dropUnique(['lark_open_id']);
            $table->dropColumn('lark_open_id');
        });
    }
};
