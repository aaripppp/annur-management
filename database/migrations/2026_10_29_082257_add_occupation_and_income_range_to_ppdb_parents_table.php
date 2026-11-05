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
        Schema::table('ppdb_parents', function (Blueprint $table) {
            $table->string('occupation', 255)->nullable()->after('no_telp');
            $table->string('income_range', 40)->nullable()->after('occupation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_parents', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'income_range']);
        });
    }
};
