<?php

use App\Enums\SchoolLevel;
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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_TU_UNIT])
                ->default(User::ROLE_ADMIN)
                ->change();

            $table->enum('school_level', array_column(SchoolLevel::cases(), 'value'))
                ->nullable()
                ->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->where('role', User::ROLE_TU_UNIT)->exists()) {
            throw new RuntimeException('Role TU Unit masih digunakan dan migration tidak dapat di-rollback dengan aman.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('school_level');

            $table->enum('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])
                ->default(User::ROLE_ADMIN)
                ->change();
        });
    }
};
