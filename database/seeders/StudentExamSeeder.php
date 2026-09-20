<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\StudentExam;
use Illuminate\Database\Seeder;

class StudentExamSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::active();

        if ($academicYear === null) {
            return;
        }

        StudentExam::query()->firstOrCreate([
            'academic_year_id' => $academicYear->id,
            'name' => 'UTS Semester Ganjil',
        ]);
    }
}
