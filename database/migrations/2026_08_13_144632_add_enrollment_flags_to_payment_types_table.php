<?php

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
        Schema::table('payment_types', function (Blueprint $table) {
            $table->boolean('is_auto_enrolled')->default(false)->after('is_active');
            $table->boolean('is_required')->default(false)->after('is_auto_enrolled');
        });

        DB::table('payment_types')
            ->where('name', 'like', '%spp%')
            ->update(['is_auto_enrolled' => true, 'is_required' => true]);

        DB::table('payment_types')
            ->where('name', 'like', '%ekskul%')
            ->update(['is_auto_enrolled' => true, 'is_required' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn(['is_auto_enrolled', 'is_required']);
        });
    }
};
