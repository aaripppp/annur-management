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
        Schema::create('student_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_category_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id');
            $table->foreignId('student_category_id');
            $table->timestamps();

            $table->foreign('student_id', 'student_category_student_student_fk')
                ->references('id')
                ->on('students')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('student_category_id', 'student_category_student_category_fk')
                ->references('id')
                ->on('student_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unique(
                ['student_id', 'student_category_id'],
                'student_category_student_unique'
            );
        });

        $now = now();

        DB::table('student_categories')->insertOrIgnore([
            ['name' => 'Anak Guru', 'code' => 'anak_guru', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Anak Yatim', 'code' => 'anak_yatim', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Beasiswa', 'code' => 'beasiswa', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_category_student');
        Schema::dropIfExists('student_categories');
    }
};
