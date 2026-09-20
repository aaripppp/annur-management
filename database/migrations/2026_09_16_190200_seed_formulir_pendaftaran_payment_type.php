<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $type = PaymentType::query()->where('name', 'Formulir Pendaftaran')->first();

            if ($type === null) {
                $type = PaymentType::create([
                    'name' => 'Formulir Pendaftaran',
                    'audience' => PaymentTypeAudience::ProspectiveStudent,
                    'is_active' => true,
                    'is_auto_enrolled' => false,
                    'is_required' => false,
                ]);
            } else {
                $type->update([
                    'audience' => PaymentTypeAudience::ProspectiveStudent,
                    'is_active' => true,
                ]);
            }

            foreach (SchoolLevel::cases() as $schoolLevel) {
                PaymentTypeSchoolLevel::query()->firstOrCreate(
                    [
                        'payment_type_id' => $type->id,
                        'school_level' => $schoolLevel,
                    ],
                    [
                        'is_active' => true,
                        'is_required' => false,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $type = PaymentType::query()->where('name', 'Formulir Pendaftaran')->first();

            if ($type === null) {
                return;
            }

            PaymentTypeSchoolLevel::query()
                ->where('payment_type_id', $type->id)
                ->where('is_required', false)
                ->delete();

            $type->update(['audience' => PaymentTypeAudience::Student]);
        });
    }
};