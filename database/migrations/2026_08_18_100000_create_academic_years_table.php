<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('year', 9)->unique();
            $table->boolean('is_active')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        $now = now();
        $activeYear = $now->month >= 7
            ? $now->year.'/'.($now->year + 1)
            : ($now->year - 1).'/'.$now->year;

        $startYear = (int) substr($activeYear, 0, 4);
        $startDate = "{$startYear}-07-01";
        $endDate = ($startYear + 1).'-06-30';

        DB::table('academic_years')->insert([
            'year' => $activeYear,
            'is_active' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
