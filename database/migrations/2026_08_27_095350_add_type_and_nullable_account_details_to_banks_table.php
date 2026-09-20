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
        Schema::table('banks', function (Blueprint $table) {
            $table->enum('type', ['bank', 'cash'])->default('bank')->after('name');
            $table->string('account_number', 100)->nullable()->change();
            $table->string('account_name', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->string('account_number', 100)->nullable(false)->change();
            $table->string('account_name', 255)->nullable(false)->change();
        });
    }
};
