<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('github_deployed_repos')->where('domain', '')->update(['domain' => null]);
    }

    public function down(): void
    {
        // Intentionally irreversible: null is the canonical representation for no domain.
    }
};
