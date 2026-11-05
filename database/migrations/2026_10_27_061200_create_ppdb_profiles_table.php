<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppdb_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospective_student_id')
                ->unique()
                ->constrained('prospective_students')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('tempat_lahir', 255)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_profiles');
    }
};
