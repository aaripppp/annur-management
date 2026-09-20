<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_promotion_rules', function (Blueprint $table) {
            $table->string('source_type', 20)->default('manual')->after('target_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_promotion_rules', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
