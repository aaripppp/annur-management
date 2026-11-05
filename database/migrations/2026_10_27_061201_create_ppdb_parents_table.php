<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppdb_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id')
                ->constrained('prospective_students')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('nama', 255)->nullable();
            $table->string('no_telp', 50)->nullable();
            $table->timestamps();

            $table->unique(['prospective_student_id', 'role'], 'ppdb_parents_ps_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_parents');
    }
};
