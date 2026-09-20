<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('school_classes')->exists()) {
            return;
        }

        $existingKb = DB::table('school_classes')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['kb'])
            ->first();

        if ($existingKb !== null) {
            DB::table('school_classes')->where('id', $existingKb->id)->update([
                'name' => 'KB',
                'level' => -3,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('school_classes')->insert([
            'name' => 'KB',
            'level' => -3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve master data and any relationships created after deployment.
    }
};
