<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_deployed_repos', function (Blueprint $table) {
            $table->unique('domain', 'github_deployed_repos_domain_unique');
        });
    }

    public function down(): void
    {
        Schema::table('github_deployed_repos', function (Blueprint $table) {
            $table->dropUnique('github_deployed_repos_domain_unique');
        });
    }
};
