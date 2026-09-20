<?php

namespace Database\Seeders;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed master data only: classes, payment types, rates, banks, academic years.
 *
 * This seeder creates NO students and NO operational data.
 * Safe to run multiple times (idempotent).
 *
 * Usage:
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=MasterDataSeeder
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdminUser();
        $this->seedPaymentTypes();
        $this->seedSchoolLevelDefaults();
        $this->seedPaymentRates();
        $this->seedBanks();
        $this->seedSchoolClasses();
        $this->seedAcademicYears();
    }

    /**
     * Baseline admin account for development login.
     */
    protected function seedAdminUser(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@annur.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
            ]
        );
    }

    /**
     * 10 payment types with correct billing frequencies and flags.
     */
    protected function seedPaymentTypes(): void
    {
        $types = [
            ['name' => 'Uang Pangkal', 'is_auto_enrolled' => false, 'is_required' => false],
            ['name' => 'Uang Kegiatan', 'is_auto_enrolled' => false, 'is_required' => false],
            ['name' => 'Uang Buku', 'is_auto_enrolled' => false, 'is_required' => false],
            ['name' => 'Labotarium', 'is_auto_enrolled' => false, 'is_required' => false],
            ['name' => 'Adm Jemputan', 'is_auto_enrolled' => false, 'is_required' => false],
            ['name' => 'Jemputan', 'is_auto_enrolled' => false, 'is_required' => false],
            ['name' => 'SPP', 'is_auto_enrolled' => true, 'is_required' => true],
            ['name' => 'Ekskul', 'is_auto_enrolled' => true, 'is_required' => true],
            ['name' => 'OSIS', 'is_auto_enrolled' => true, 'is_required' => true],
            ['name' => 'Lainnya', 'is_auto_enrolled' => false, 'is_required' => false],
        ];

        foreach ($types as $type) {
            PaymentType::updateOrCreate(
                ['name' => $type['name']],
                ['is_active' => true] + $type
            );
        }
    }

    /**
     * Default billing applicability per school level.
     *
     * TK:   SPP
     * SD:   SPP, Ekskul
     * SMP:  SPP, Ekskul, OSIS
     * SMA:  SPP, Ekskul
     *
     * Biaya tahunan dan sekali bayar standar berlaku otomatis di semua jenjang.
     * Jenis lain tetap manual kecuali tercantum pada konfigurasi ini.
     */
    protected function seedSchoolLevelDefaults(): void
    {
        $defaults = [
            SchoolLevel::TK->value => ['SPP', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
            SchoolLevel::SD->value => ['SPP', 'Ekskul', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
            SchoolLevel::SMP->value => ['SPP', 'Ekskul', 'OSIS', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
            SchoolLevel::SMA->value => ['SPP', 'Ekskul', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
        ];
        $requiredTypeNames = ['SPP', 'Ekskul', 'OSIS'];

        foreach ($defaults as $level => $typeNames) {
            foreach ($typeNames as $typeName) {
                $type = PaymentType::where('name', $typeName)->firstOrFail();

                PaymentTypeSchoolLevel::updateOrCreate(
                    ['payment_type_id' => $type->id, 'school_level' => $level],
                    [
                        'is_required' => in_array($typeName, $requiredTypeNames, true),
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Baseline payment rates for the previously configured levels.
     *
     * Rates apply by numeric level:
     * - TKA = level -2, TKB = level -1
     * - SD  = levels 1-6
     * - SMP = levels 7-9
     * - SMA = levels 10-12
     *
     * KB rates are intentionally configured later through Tarif Pembayaran.
     *
     * TKA and TKB have independent rate records (same amount now,
     * but can diverge later).
     *
     * Exception: OSIS is SMP-only (levels 7-9).
     */
    protected function seedPaymentRates(): void
    {
        $effectiveFrom = Carbon::parse('2026-01-01');

        $allLevels = [-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

        $rates = [
            'SPP' => ['amount' => 970000, 'frequency' => BillFrequency::Monthly, 'levels' => $allLevels],
            'Ekskul' => ['amount' => 60000, 'frequency' => BillFrequency::Monthly, 'levels' => $allLevels],
            'OSIS' => ['amount' => 5000, 'frequency' => BillFrequency::Monthly, 'levels' => [7, 8, 9]],
            'Jemputan' => ['amount' => 500000, 'frequency' => BillFrequency::Monthly, 'levels' => $allLevels],
            'Adm Jemputan' => ['amount' => 50000, 'frequency' => BillFrequency::Monthly, 'levels' => $allLevels],
            'Uang Pangkal' => ['amount' => 10000000, 'frequency' => BillFrequency::OneTime, 'levels' => $allLevels],
            'Uang Buku' => ['amount' => 1000000, 'frequency' => BillFrequency::Yearly, 'levels' => $allLevels],
            'Uang Kegiatan' => ['amount' => 2500000, 'frequency' => BillFrequency::Yearly, 'levels' => $allLevels],
            'Labotarium' => ['amount' => 100000, 'frequency' => BillFrequency::OneTime, 'levels' => $allLevels],
            'Lainnya' => ['amount' => 50000, 'frequency' => BillFrequency::Monthly, 'levels' => $allLevels],
        ];

        foreach ($rates as $typeName => $config) {
            $type = PaymentType::where('name', $typeName)->firstOrFail();

            foreach ($config['levels'] as $level) {
                PaymentRate::updateOrCreate(
                    ['payment_type_id' => $type->id, 'class_level' => $level, 'effective_from' => $effectiveFrom],
                    [
                        'amount' => $config['amount'],
                        'billing_frequency' => $config['frequency'],
                        'effective_until' => null,
                    ]
                );
            }
        }
    }

    /**
     * 5 default banks.
     */
    protected function seedBanks(): void
    {
        $banks = [
            ['name' => 'BSI', 'account_number' => '7123456789', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'Mandiri', 'account_number' => '9876543210', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'BRI', 'account_number' => '1122334455', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'BNI', 'account_number' => '5566778899', 'account_name' => 'Yayasan Sekolah'],
        ];

        foreach ($banks as $bank) {
            Bank::firstOrCreate(['name' => $bank['name']], $bank);
        }
    }

    /**
     * 64 school classes across TK/SD/SMP/SMA.
     *
     * KB:  one class (level -3)
     * TKA: A-1..A-5 (level -2)
     * TKB: B-1..B-5 (level -1)
     * SD:  I-VI × A-E (levels 1-6, 5 sections each)
     * SMP: VII-IX × A-E (levels 7-9, 5 sections each)
     * SMA: X-A/B, XI-A/B, XII IPA-A/B, XII IPS-A/B (levels 10-12)
     */
    protected function seedSchoolClasses(): void
    {
        foreach ($this->classDefinitions() as $def) {
            SchoolClass::updateOrCreate(
                ['name' => $def['name']],
                ['level' => $def['level']]
            );
        }
    }

    /**
     * @return array<int, array{name: string, level: int}>
     */
    protected function classDefinitions(): array
    {
        $classes = [];
        $sections = ['A', 'B', 'C', 'D', 'E'];
        $tkSections = ['1', '2', '3', '4', '5'];

        $classes[] = ['name' => 'KB', 'level' => -3];

        // TKA — level -2
        foreach ($tkSections as $s) {
            $classes[] = ['name' => "A-$s", 'level' => -2];
        }

        // TKB — level -1
        foreach ($tkSections as $s) {
            $classes[] = ['name' => "B-$s", 'level' => -1];
        }

        // SD — levels 1-6, roman numeral + section
        $romans = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI'];
        foreach ($romans as $level => $roman) {
            foreach ($sections as $s) {
                $classes[] = ['name' => "$roman $s", 'level' => $level];
            }
        }

        // SMP — levels 7-9
        $smpRomans = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
        foreach ($smpRomans as $level => $roman) {
            foreach ($sections as $s) {
                $classes[] = ['name' => "$roman $s", 'level' => $level];
            }
        }

        // SMA — levels 10-12
        $classes[] = ['name' => 'X-A', 'level' => 10];
        $classes[] = ['name' => 'X-B', 'level' => 10];
        $classes[] = ['name' => 'XI-A', 'level' => 11];
        $classes[] = ['name' => 'XI-B', 'level' => 11];
        $classes[] = ['name' => 'XII IPA-A', 'level' => 12];
        $classes[] = ['name' => 'XII IPA-B', 'level' => 12];
        $classes[] = ['name' => 'XII IPS-A', 'level' => 12];
        $classes[] = ['name' => 'XII IPS-B', 'level' => 12];

        return $classes;
    }

    /**
     * 2 academic years: current (active) and future (inactive).
     */
    protected function seedAcademicYears(): void
    {
        AcademicYear::updateOrCreate(
            ['year' => '2026/2027'],
            [
                'is_active' => true,
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'promotion_processed_at' => null,
            ]
        );

        AcademicYear::updateOrCreate(
            ['year' => '2027/2028'],
            [
                'is_active' => false,
                'start_date' => '2027-07-01',
                'end_date' => '2028-06-30',
                'promotion_processed_at' => null,
            ]
        );
    }
}
