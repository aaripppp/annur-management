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
        Schema::create('ppdb_academic_year_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id');
            $table->boolean('is_open')->default(false);
            $table->timestamps();

            $table->unique('academic_year_id', 'ppdb_ays_ay_unique');
            $table->foreign('academic_year_id', 'ppdb_ays_ay_fk')
                ->references('id')
                ->on('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_academic_year_settings');
    }
};
