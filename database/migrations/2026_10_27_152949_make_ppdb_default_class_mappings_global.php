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
        $tableName = 'ppdb_default_class_mappings';

        if (! Schema::hasTable($tableName)) {
            throw new RuntimeException('Cannot make PPDB default classes global: the mapping table does not exist.');
        }

        $requiredColumns = ['id', 'academic_year_id', 'class_level', 'school_class_id', 'created_at', 'updated_at'];
        if (! Schema::hasColumns($tableName, $requiredColumns)) {
            throw new RuntimeException('Cannot make PPDB default classes global: the existing mapping schema is not recognized.');
        }

        if (! Schema::hasIndex($tableName, 'ppdb_dcm_ay_cl_unique', 'unique')
            || ! Schema::hasIndex($tableName, 'ppdb_dcm_ay_sc_unique', 'unique')
            || ! Schema::hasIndex($tableName, 'ppdb_dcm_sc_idx')
            || ! Schema::hasForeignKey($tableName, ['academic_year_id'])
            || ! Schema::hasForeignKey($tableName, ['school_class_id'])) {
            throw new RuntimeException('Cannot make PPDB default classes global: required indexes or foreign keys are missing.');
        }

        $conflictingLevels = DB::table($tableName)
            ->select('class_level')
            ->groupBy('class_level')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('class_level')
            ->pluck('class_level');

        if ($conflictingLevels->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot make PPDB default classes global. Resolve duplicate class levels manually first: '
                .$conflictingLevels->implode(', ')
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE `ppdb_default_class_mappings`
                    DROP FOREIGN KEY `ppdb_default_class_mappings_academic_year_id_foreign`,
                    DROP INDEX `ppdb_dcm_ay_cl_unique`,
                    DROP INDEX `ppdb_dcm_ay_sc_unique`,
                    DROP COLUMN `academic_year_id`,
                    ADD UNIQUE INDEX `ppdb_dcm_cl_unique` (`class_level`)
                SQL);

            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropForeign(['academic_year_id']);
            $table->dropUnique('ppdb_dcm_ay_cl_unique');
            $table->dropUnique('ppdb_dcm_ay_sc_unique');
            $table->dropColumn('academic_year_id');
            $table->unique('class_level', 'ppdb_dcm_cl_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'This migration cannot safely restore historical academic-year associations. Restore the pre-migration database backup instead.'
        );
    }
};
