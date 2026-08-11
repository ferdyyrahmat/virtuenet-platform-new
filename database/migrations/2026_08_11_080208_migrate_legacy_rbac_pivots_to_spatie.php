<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('permission_role') && Schema::hasTable('role_has_permissions')) {
            DB::table('permission_role')->get()->each(function ($row): void {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $row->permission_id,
                    'role_id' => $row->role_id,
                ]);
            });
        }

        if (Schema::hasTable('role_user') && Schema::hasTable('model_has_roles')) {
            DB::table('role_user')->get()->each(function ($row): void {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $row->role_id,
                    'model_type' => User::class,
                    'model_id' => $row->user_id,
                ]);
            });
        }

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        DB::table('role_has_permissions')->get()->each(function ($row): void {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $row->permission_id,
                'role_id' => $row->role_id,
            ]);
        });

        DB::table('model_has_roles')->where('model_type', User::class)->get()->each(function ($row): void {
            DB::table('role_user')->insertOrIgnore([
                'role_id' => $row->role_id,
                'user_id' => $row->model_id,
            ]);
        });
    }
};
