<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProspectiveStudentReceiptNumberGenerator
{
    public function next(int $year): string
    {
        return DB::transaction(function () use ($year): string {
            DB::table('prospective_student_payment_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('prospective_student_payment_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException('Sequence kwitansi calon siswa gagal dibuat.');
            }

            $nextNumber = (int) $sequence->last_number + 1;

            DB::table('prospective_student_payment_sequences')
                ->where('year', $year)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return sprintf('KWT-REG-%d-%06d', $year, $nextNumber);
        });
    }
}
