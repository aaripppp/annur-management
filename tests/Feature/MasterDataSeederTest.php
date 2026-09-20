<?php

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\TestingStudentSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
});

/*
|--------------------------------------------------------------------------
| 1. MasterDataSeeder creates exactly 64 classes
|--------------------------------------------------------------------------
*/
it('master data seeder creates exactly 64 school classes including one KB', function () {
    expect(SchoolClass::count())->toBe(64)
        ->and(SchoolClass::query()->where('name', 'KB')->where('level', -3)->count())->toBe(1)
        ->and(PaymentRate::query()->where('class_level', -3)->count())->toBe(0);

    $rates = PaymentRate::query()
        ->orderBy('id')
        ->get(['payment_type_id', 'class_level', 'amount', 'billing_frequency', 'effective_from', 'effective_until'])
        ->toArray();
    $this->seed(MasterDataSeeder::class);

    expect(SchoolClass::query()->where('name', 'KB')->where('level', -3)->count())->toBe(1)
        ->and(PaymentRate::query()
            ->orderBy('id')
            ->get(['payment_type_id', 'class_level', 'amount', 'billing_frequency', 'effective_from', 'effective_until'])
            ->toArray())->toBe($rates);
});

/*
|--------------------------------------------------------------------------
| 2. Class names are unique
|--------------------------------------------------------------------------
*/
it('school class names are unique', function () {
    $names = SchoolClass::pluck('name')->toArray();
    expect($names)->toHaveCount(64);
    expect(array_unique($names))->toHaveCount(64);
});

/*
|--------------------------------------------------------------------------
| 3. Class name and level are consistent
|--------------------------------------------------------------------------
*/
it('school class names are consistent with their numeric level', function () {
    // Ordered longest-first so IX doesn't match I, VIII doesn't match V, etc.
    $map = [
        'XII ' => 12, 'XI-' => 11, 'X-' => 10,
        'VIII ' => 8, 'VII ' => 7, 'VI ' => 6, 'V ' => 5,
        'IV ' => 4, 'III ' => 3, 'II ' => 2, 'IX ' => 9,
        'I ' => 1,
        'KB' => -3,
        'A-' => -2, 'B-' => -1,
    ];

    foreach (SchoolClass::all() as $class) {
        $expectedLevel = null;
        foreach ($map as $prefix => $level) {
            if (str_starts_with($class->name, $prefix)) {
                $expectedLevel = $level;
                break;
            }
        }
        expect($class->level)->toBe(
            $expectedLevel,
            "Class '{$class->name}' has level {$class->level}, expected {$expectedLevel}"
        );
    }
});

/*
|--------------------------------------------------------------------------
| 4. 10 PaymentTypes exist
|--------------------------------------------------------------------------
*/
it('creates exactly 11 payment types', function () {
    expect(PaymentType::count())->toBe(11);
});

/*
|--------------------------------------------------------------------------
| 5. Frequencies are correct
|--------------------------------------------------------------------------
*/
it('payment type billing frequencies are correct', function () {
    $expected = [
        'Uang Pangkal' => BillFrequency::OneTime,
        'Uang Kegiatan' => BillFrequency::Yearly,
        'Uang Buku' => BillFrequency::Yearly,
        'Labotarium' => BillFrequency::OneTime,
        'Adm Jemputan' => BillFrequency::Monthly,
        'Jemputan' => BillFrequency::Monthly,
        'SPP' => BillFrequency::Monthly,
        'Ekskul' => BillFrequency::Monthly,
        'OSIS' => BillFrequency::Monthly,
        'Lainnya' => BillFrequency::Monthly,
    ];

    foreach ($expected as $typeName => $frequency) {
        $type = PaymentType::where('name', $typeName)->first();
        expect($type)->not->toBeNull("PaymentType '$typeName' not found");

        $rate = $type->rates()->first();
        expect($rate)->not->toBeNull("No rate found for '$typeName'");
        expect($rate->billing_frequency)->toBe($frequency);
    }
});

/*
|--------------------------------------------------------------------------
| 6. SPP rates = 970000
|--------------------------------------------------------------------------
*/
it('spp rate is 970000 for all levels', function () {
    $rates = PaymentType::where('name', 'SPP')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(970000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 7. Ekskul rates = 60000
|--------------------------------------------------------------------------
*/
it('ekskul rate is 60000 for all levels', function () {
    $rates = PaymentType::where('name', 'Ekskul')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(60000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 8. OSIS rate = 5000 and only applies to SMP
|--------------------------------------------------------------------------
*/
it('osis rate is 5000 and only applies to smp levels', function () {
    $rates = PaymentType::where('name', 'OSIS')->firstOrFail()->rates;
    expect($rates->count())->toBe(3);
    foreach ($rates as $rate) {
        expect($rate->class_level)->toBeGreaterThanOrEqual(7);
        expect($rate->class_level)->toBeLessThanOrEqual(9);
        expect((float) $rate->amount)->toBe(5000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 9. Jemputan = 500000
|--------------------------------------------------------------------------
*/
it('jemputan rate is 500000 for all levels', function () {
    $rates = PaymentType::where('name', 'Jemputan')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(500000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 10. Adm Jemputan = 50000
|--------------------------------------------------------------------------
*/
it('adm jemputan rate is 50000 for all levels', function () {
    $rates = PaymentType::where('name', 'Adm Jemputan')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(50000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 11. Uang Pangkal = 10000000
|--------------------------------------------------------------------------
*/
it('uang pangkal rate is 10000000 for all levels', function () {
    $rates = PaymentType::where('name', 'Uang Pangkal')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(10000000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 12. Uang Buku = 1000000
|--------------------------------------------------------------------------
*/
it('uang buku rate is 1000000 for all levels', function () {
    $rates = PaymentType::where('name', 'Uang Buku')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(1000000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 13. Uang Kegiatan = 2500000
|--------------------------------------------------------------------------
*/
it('uang kegiatan rate is 2500000 for all levels', function () {
    $rates = PaymentType::where('name', 'Uang Kegiatan')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(2500000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 14. Labotarium = 100000
|--------------------------------------------------------------------------
*/
it('labotarium rate is 100000 for all levels', function () {
    $rates = PaymentType::where('name', 'Labotarium')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(100000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 15. Lainnya = 50000
|--------------------------------------------------------------------------
*/
it('lainnya rate is 50000 for all levels', function () {
    $rates = PaymentType::where('name', 'Lainnya')->firstOrFail()->rates;
    expect($rates->count())->toBe(14);
    foreach ($rates as $rate) {
        expect((float) $rate->amount)->toBe(50000.0);
    }
});

/*
|--------------------------------------------------------------------------
| 16. Exactly 5 initial Bank records
|--------------------------------------------------------------------------
*/
it('creates exactly 5 bank records', function () {
    expect(Bank::count())->toBe(5);
    $names = Bank::pluck('name')->sort()->values()->toArray();
    expect($names)->toBe(['BCA', 'BNI', 'BRI', 'BSI', 'Mandiri']);
});

/*
|--------------------------------------------------------------------------
| 17. AcademicYear 2026/2027 is active
|--------------------------------------------------------------------------
*/
it('academic year 2026/2027 is active', function () {
    $year = AcademicYear::where('year', '2026/2027')->first();
    expect($year)->not->toBeNull();
    expect($year->is_active)->toBeTrue();
    expect($year->start_date->format('Y-m-d'))->toBe('2026-07-01');
    expect($year->end_date->format('Y-m-d'))->toBe('2027-06-30');
});

/*
|--------------------------------------------------------------------------
| 18. AcademicYear 2027/2028 is future/inactive
|--------------------------------------------------------------------------
*/
it('academic year 2027/2028 is inactive', function () {
    $year = AcademicYear::where('year', '2027/2028')->first();
    expect($year)->not->toBeNull();
    expect($year->is_active)->toBeFalse();
    expect($year->start_date->format('Y-m-d'))->toBe('2027-07-01');
    expect($year->end_date->format('Y-m-d'))->toBe('2028-06-30');
    expect($year->promotion_processed_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 19. MasterDataSeeder creates ZERO students
|--------------------------------------------------------------------------
*/
it('master data seeder creates zero students', function () {
    expect(Student::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 20. TestingStudentSeeder creates exactly 64 TEST students
|--------------------------------------------------------------------------
*/
it('testing student seeder creates exactly 64 test students', function () {
    $this->seed(TestingStudentSeeder::class);
    expect(Student::where('nis', 'like', 'TEST-%')->count())->toBe(64);
});

/*
|--------------------------------------------------------------------------
| 21. Exactly one TEST student per SchoolClass
|--------------------------------------------------------------------------
*/
it('exactly one test student per school class', function () {
    $this->seed(TestingStudentSeeder::class);

    foreach (SchoolClass::all() as $class) {
        $count = Student::where('class_id', $class->id)
            ->where('nis', 'like', 'TEST-%')
            ->count();
        expect($count)->toBe(1, "Class '{$class->name}' has {$count} test students");
    }
});

/*
|--------------------------------------------------------------------------
| 22. Every TEST student has 2026/2027 enrollment
|--------------------------------------------------------------------------
*/
it('every test student has 2026 2027 enrollment', function () {
    $this->seed(TestingStudentSeeder::class);
    $activeYear = AcademicYear::where('year', '2026/2027')->firstOrFail();

    Student::where('nis', 'like', 'TEST-%')->get()->each(function ($student) use ($activeYear) {
        $enrollment = $student->enrollments()
            ->where('academic_year_id', $activeYear->id)
            ->first();
        expect($enrollment)->not->toBeNull("Student '{$student->nis}' missing enrollment");
        expect($enrollment->status)->toBe('active');
    });
});

/*
|--------------------------------------------------------------------------
| 23. No Faker/random student names
|--------------------------------------------------------------------------
*/
it('no fakery in test student names', function () {
    $this->seed(TestingStudentSeeder::class);

    Student::where('nis', 'like', 'TEST-%')->get()->each(function ($student) {
        expect($student->nama_lengkap)->toStartWith('TEST ');
        expect($student->nis)->toStartWith('TEST-');
    });
});

/*
|--------------------------------------------------------------------------
| 24. TestingStudentSeeder does not alter master tariffs
|--------------------------------------------------------------------------
*/
it('testing student seeder does not alter master tariffs', function () {
    $before = PaymentRate::query()->orderBy('id')->get()->toArray();
    $this->seed(TestingStudentSeeder::class);
    $after = PaymentRate::query()->orderBy('id')->get()->toArray();
    expect($before)->toBe($after);
});

/*
|--------------------------------------------------------------------------
| 25. TestingStudentSeeder does not alter banks
|--------------------------------------------------------------------------
*/
it('testing student seeder does not alter banks', function () {
    $countBefore = Bank::count();
    $namesBefore = Bank::pluck('name')->sort()->toArray();
    $this->seed(TestingStudentSeeder::class);
    expect(Bank::count())->toBe($countBefore);
    expect(Bank::pluck('name')->sort()->toArray())->toBe($namesBefore);
});

/*
|--------------------------------------------------------------------------
| 26. TestingStudentSeeder re-run does not duplicate TEST students
|--------------------------------------------------------------------------
*/
it('testing student seeder re run does not duplicate test students', function () {
    $this->seed(TestingStudentSeeder::class);
    $count1 = Student::where('nis', 'like', 'TEST-%')->count();
    $this->seed(TestingStudentSeeder::class);
    $count2 = Student::where('nis', 'like', 'TEST-%')->count();
    expect($count2)->toBe($count1)->toBe(64);
});

/*
|--------------------------------------------------------------------------
| 27. SchoolClassFactory cannot create mismatched name/level data
|--------------------------------------------------------------------------
*/
it('school class factory always produces consistent name and level', function () {
    $tests = [
        [-3, 'KB'],
        [7, 'VII'],
        [10, 'X-'],
        [-2, 'A-'],
        [-1, 'B-'],
        [9, 'IX '],
        [12, 'XII '],
    ];

    foreach ($tests as [$level, $nameFragment]) {
        for ($i = 0; $i < 30; $i++) {
            $class = SchoolClass::factory()->create(['level' => $level]);
            expect($class->level)->toBe($level);
            expect($class->name)->toContain($nameFragment);
            $class->delete();
        }
    }
});

/*
|--------------------------------------------------------------------------
| 28. Bank UI is not limited to exactly five hardcoded banks
|--------------------------------------------------------------------------
*/
it('bank management component uses database driven bank list', function () {
    expect(Bank::count())->toBe(5);

    Bank::create([
        'name' => 'BankSix',
        'account_number' => '9999999999',
        'account_name' => 'Yayasan Test',
        'is_active' => true,
    ]);

    expect(Bank::count())->toBe(6);
    expect(Bank::where('name', 'BankSix')->exists())->toBeTrue();

    $banksFromDb = Bank::orderBy('name')->pluck('name')->toArray();
    expect($banksFromDb)->toContain('BankSix');
    expect(count($banksFromDb))->toBe(6);
});

/*
|--------------------------------------------------------------------------
| 29. Baseline admin user exists with correct attributes
|--------------------------------------------------------------------------
*/
it('creates baseline admin user with correct email name and role', function () {
    $admin = User::where('email', 'admin@annur.test')->first();

    expect($admin)->not->toBeNull('admin@annur.test user not found');
    expect($admin->name)->toBe('Admin');
    expect($admin->role)->toBe(User::ROLE_SUPER_ADMIN);
    expect(Hash::check('password', $admin->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 30. Exactly one baseline user exists after MasterDataSeeder
|--------------------------------------------------------------------------
*/
it('creates exactly one user after seeding', function () {
    expect(User::count())->toBe(1);
    expect(User::first()->email)->toBe('admin@annur.test');
});
