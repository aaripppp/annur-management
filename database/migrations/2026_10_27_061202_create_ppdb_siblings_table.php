<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppdb_siblings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id')
                ->constrained('prospective_students')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('nama_lengkap', 255);
            $table->timestamps();

            $table->unique(['prospective_student_id', 'sort_order'], 'ppdb_siblings_ps_so_unique');
            $table->index('prospective_student_id', 'ppdb_siblings_ps_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_siblings');
    }
};
