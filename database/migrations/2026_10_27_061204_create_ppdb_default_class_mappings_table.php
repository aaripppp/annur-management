<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppdb_default_class_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->smallInteger('class_level');

            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'class_level'], 'ppdb_dcm_ay_cl_unique');
            $table->unique(['academic_year_id', 'school_class_id'], 'ppdb_dcm_ay_sc_unique');
            $table->index('school_class_id', 'ppdb_dcm_sc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_default_class_mappings');
    }
};
