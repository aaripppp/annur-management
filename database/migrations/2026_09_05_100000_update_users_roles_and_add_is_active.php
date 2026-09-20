<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('role')
            ->orWhereNotIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
            ->update(['role' => User::ROLE_ADMIN]);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
                ->default(User::ROLE_ADMIN)
                ->change();

            $table->boolean('is_active')->default(true)->after('role');
        });

        $users = DB::table('users')->orderBy('id')->get();

        $hasActiveSuperAdmin = $users->contains(
            static fn (object $user): bool => $user->role === User::ROLE_SUPER_ADMIN && (bool) $user->is_active
        );

        if ($users->isNotEmpty() && ! $hasActiveSuperAdmin) {
            DB::table('users')->where('id', $users->first()->id)->update([
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('role', User::ROLE_SUPER_ADMIN)->update(['role' => User::ROLE_ADMIN]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');

            $table->enum('role', ['admin', 'tu'])->default('tu')->change();
        });
    }
};
