<?php

use App\Enums\BillFrequency;
use App\Livewire\PaymentRateManagement;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use Database\Seeders\SchoolDataSeeder;
use Livewire\Livewire;

it('membuat tarif bulanan', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('class_level', 8)
        ->set('amount', 350000)
        ->set('billing_frequency', 'monthly')
        ->set('effective_from', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors();

    $rate = PaymentRate::where('payment_type_id', $type->id)->where('class_level', 8)->first();

    expect($rate)->not->toBeNull()
        ->and($rate->amount)->toBe('350000.00')
        ->and($rate->billing_frequency)->toBe(BillFrequency::Monthly)
        ->and($rate->is_monthly)->toBeTrue();
});

it('membuat tarif tahunan', function () {
    $type = PaymentType::factory()->create(['name' => 'Uang Buku']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('class_level', 8)
        ->set('amount', 500000)
        ->set('billing_frequency', 'yearly')
        ->set('effective_from', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors();

    $rate = PaymentRate::where('payment_type_id', $type->id)->where('class_level', 8)->first();

    expect($rate)->not->toBeNull()
        ->and($rate->amount)->toBe('500000.00')
        ->and($rate->billing_frequency)->toBe(BillFrequency::Yearly)
        ->and($rate->is_monthly)->toBeFalse();
});

it('membuat tarif sekali bayar', function () {
    $type = PaymentType::factory()->create(['name' => 'Uang Pangkal']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('class_level', 8)
        ->set('amount', 2000000)
        ->set('billing_frequency', 'one_time')
        ->set('effective_from', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors();

    $rate = PaymentRate::where('payment_type_id', $type->id)->where('class_level', 8)->first();

    expect($rate)->not->toBeNull()
        ->and($rate->billing_frequency)->toBe(BillFrequency::OneTime)
        ->and($rate->is_monthly)->toBeFalse();
});

it('membuat tarif bulanan untuk seluruh level SMP secara idempotent', function () {
    $type = PaymentType::factory()->create(['name' => 'Iuran Digital']);

    $component = Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('target_scope', 'school_level')
        ->set('school_level', 'SMP')
        ->set('amount', 100000)
        ->set('billing_frequency', 'monthly')
        ->set('effective_from', '2028-07-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(PaymentRate::where('payment_type_id', $type->id)->pluck('class_level')->sort()->values()->all())->toBe([7, 8, 9])
        ->and(PaymentRate::where('payment_type_id', $type->id)->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(3);

    $component
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('target_scope', 'school_level')
        ->set('school_level', 'SMP')
        ->set('amount', 125000)
        ->set('billing_frequency', 'monthly')
        ->set('effective_from', '2028-07-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(PaymentRate::where('payment_type_id', $type->id)->count())->toBe(3)
        ->and(PaymentRate::where('payment_type_id', $type->id)->pluck('amount')->unique()->values()->all())->toBe(['125000.00']);
});

it('membuat tarif per jenjang TK pada level KB TKA dan TKB', function () {
    $type = PaymentType::factory()->create(['name' => 'Program TK']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('target_scope', 'school_level')
        ->set('school_level', 'TK')
        ->set('amount', 250000)
        ->set('billing_frequency', 'yearly')
        ->set('effective_from', '2028-07-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(PaymentRate::where('payment_type_id', $type->id)->pluck('class_level')->sort()->values()->all())->toBe([-3, -2, -1])
        ->and(PaymentRate::where('payment_type_id', $type->id)->where('billing_frequency', BillFrequency::Yearly)->count())->toBe(3);
});

it('periode tagihan memakai satu native radio group dan satu scalar state', function () {
    $component = Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->assertSet('billing_frequency', 'monthly');

    expect(substr_count($component->html(), 'name="billing_frequency"'))->toBe(3)
        ->and(substr_count($component->html(), 'wire:model="billing_frequency"'))->toBe(3);

    $component
        ->set('billing_frequency', 'yearly')
        ->assertSet('billing_frequency', 'yearly')
        ->set('billing_frequency', 'one_time')
        ->assertSet('billing_frequency', 'one_time')
        ->set('billing_frequency', 'monthly')
        ->assertSet('billing_frequency', 'monthly');
});

it('edit memuat nilai tarif yang benar berdasarkan ID', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    $rate = PaymentRate::factory()->yearly()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 450000,
        'effective_from' => '2026-08-01',
    ]);

    Livewire::test(PaymentRateManagement::class)
        ->call('edit', $rate->id)
        ->assertHasNoErrors()
        ->assertSet('isEditing', true)
        ->assertSet('rateId', $rate->id)
        ->assertSet('payment_type_id', $type->id)
        ->assertSet('class_level', 8)
        ->assertSet('amount', '450.000')
        ->assertSet('billing_frequency', 'yearly')
        ->assertSet('effective_from', '2026-08-01');
});

it('mengedit tarif tahunan tidak mengubah periodenya', function () {
    $type = PaymentType::factory()->create(['name' => 'Uang Buku']);
    $rate = PaymentRate::factory()->yearly()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 500000,
        'effective_from' => '2026-08-01',
    ]);

    Livewire::test(PaymentRateManagement::class)
        ->call('edit', $rate->id)
        ->assertSet('billing_frequency', 'yearly')
        ->set('amount', 550000)
        ->call('save')
        ->assertHasNoErrors();

    $updated = PaymentRate::find($rate->id);

    expect($updated->amount)->toBe('550000.00')
        ->and($updated->billing_frequency)->toBe(BillFrequency::Yearly)
        ->and($updated->is_monthly)->toBeFalse();
});

it('filter jenis menampilkan hanya tarif jenis tersebut', function () {
    $typeA = PaymentType::factory()->create(['name' => 'SPP']);
    $typeB = PaymentType::factory()->create(['name' => 'Uang Buku']);

    $rateA = PaymentRate::factory()->create(['payment_type_id' => $typeA->id, 'class_level' => 8, 'amount' => 970000]);
    $rateB = PaymentRate::factory()->create(['payment_type_id' => $typeB->id, 'class_level' => 8, 'amount' => 500000]);

    Livewire::test(PaymentRateManagement::class)
        ->set('filterPaymentTypeId', $typeA->id)
        ->assertSee('payment-rate-'.$rateA->id, false)
        ->assertDontSee('payment-rate-'.$rateB->id, false);

    Livewire::test(PaymentRateManagement::class)
        ->set('filterPaymentTypeId', $typeB->id)
        ->assertDontSee('payment-rate-'.$rateA->id, false)
        ->assertSee('payment-rate-'.$rateB->id, false);
});

it('setelah filter, edit menargetkan tarif yang benar', function () {
    $typeA = PaymentType::factory()->create(['name' => 'SPP']);
    $typeB = PaymentType::factory()->create(['name' => 'Uang Buku']);

    PaymentRate::factory()->create([
        'payment_type_id' => $typeA->id,
        'class_level' => 8,
        'amount' => 970000,
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2026-08-01',
    ]);
    $rateB = PaymentRate::factory()->create([
        'payment_type_id' => $typeB->id,
        'class_level' => 8,
        'amount' => 500000,
        'billing_frequency' => BillFrequency::Yearly,
        'effective_from' => '2026-08-01',
    ]);

    Livewire::test(PaymentRateManagement::class)
        ->set('filterPaymentTypeId', $typeA->id)
        ->call('edit', $rateB->id)
        ->assertHasNoErrors()
        ->assertSet('rateId', $rateB->id)
        ->assertSet('payment_type_id', $typeB->id)
        ->assertSet('amount', '500.000')
        ->assertSet('billing_frequency', 'yearly');
});

it('setelah filter kelas, delete menghapus tarif yang benar', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    $level7 = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'class_level' => 7,
        'amount' => 960000,
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2026-08-01',
    ]);
    $level8 = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 970000,
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2026-08-01',
    ]);

    Livewire::test(PaymentRateManagement::class)
        ->set('filterClassLevel', 8)
        ->call('confirmDelete', $level7->id)
        ->assertSet('deletingId', $level7->id)
        ->call('delete');

    expect(PaymentRate::find($level7->id))->toBeNull()
        ->and(PaymentRate::find($level8->id))->not->toBeNull();
});

it('menghapus tarif tanpa mengganggu tarif lain', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    $target = PaymentRate::factory()->create(['payment_type_id' => $type->id, 'class_level' => 8]);
    $other = PaymentRate::factory()->create(['payment_type_id' => $type->id, 'class_level' => 7]);

    Livewire::test(PaymentRateManagement::class)
        ->call('confirmDelete', $target->id)
        ->call('delete');

    expect(PaymentRate::find($target->id))->toBeNull()
        ->and(PaymentRate::find($other->id))->not->toBeNull();
});

it('validasi menolak periode tagihan yang tidak dikenal', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('class_level', 8)
        ->set('amount', 500000)
        ->set('billing_frequency', 'semesteran')
        ->set('effective_from', '2026-08-01')
        ->call('save')
        ->assertHasErrors(['billing_frequency']);
});

it('validasi menolak nominal negatif', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('class_level', 8)
        ->set('amount', -5000)
        ->set('billing_frequency', 'monthly')
        ->set('effective_from', '2026-08-01')
        ->call('save')
        ->assertHasErrors(['amount']);
});

it('list refresh setelah create dan update tanpa reload', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    Livewire::test(PaymentRateManagement::class)
        ->call('openModal')
        ->set('payment_type_id', $type->id)
        ->set('class_level', 8)
        ->set('amount', 350000)
        ->set('billing_frequency', 'monthly')
        ->set('effective_from', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('SPP');

    $rate = PaymentRate::where('payment_type_id', $type->id)->where('class_level', 8)->firstOrFail();

    Livewire::test(PaymentRateManagement::class)
        ->call('edit', $rate->id)
        ->set('amount', 375000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('375.000');

    expect(PaymentRate::find($rate->id)->amount)->toBe('375000.00');
});

it('wire:key pada baris list menggunakan ID tarif', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);
    $rate = PaymentRate::factory()->create(['payment_type_id' => $type->id, 'class_level' => 8]);

    Livewire::test(PaymentRateManagement::class)
        ->assertSee('payment-rate-'.$rate->id, false);
});

describe('badge frekuensi Tarif Pembayaran', function () {
    it('badge Bulanan, Tahunan, dan Sekali Bayar satu baris dengan tinggi konsisten', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);

        PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 7,
            'amount' => 500000,
        ]);
        PaymentRate::factory()->yearly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 8,
            'amount' => 500000,
        ]);
        PaymentRate::factory()->oneTime()->create([
            'payment_type_id' => $type->id,
            'class_level' => 9,
            'amount' => 500000,
        ]);

        $component = Livewire::test(PaymentRateManagement::class);

        // Satu baris (whitespace-nowrap), padding & bentuk sama, tidak ada wrap di dalam label.
        $component
            ->assertSeeHtml('<span class="inline-flex items-center justify-center py-1 px-3 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-secondary-fixed text-secondary">Bulanan</span>')
            ->assertSeeHtml('<span class="inline-flex items-center justify-center py-1 px-3 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-tertiary-fixed text-tertiary">Tahunan</span>')
            ->assertSeeHtml('<span class="inline-flex items-center justify-center py-1 px-3 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-surface-container-high text-on-surface-variant">Sekali Bayar</span>');
    });

    it('badge jenjang TK dan angka kelas konsisten secara visual', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);

        PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => -2,
            'amount' => 500000,
        ]);
        PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 1,
            'amount' => 500000,
        ]);

        $component = Livewire::test(PaymentRateManagement::class);

        // Base class identik: satu baris, min-width seragam, warna jenjang sama.
        $component
            ->assertSeeHtml('<span class="inline-flex items-center justify-center py-1 px-3 rounded-full text-label-sm font-label-sm whitespace-nowrap min-w-12 bg-primary-fixed text-on-primary-fixed">TKA</span>')
            ->assertSeeHtml('<span class="inline-flex items-center justify-center py-1 px-3 rounded-full text-label-sm font-label-sm whitespace-nowrap min-w-12 bg-primary-fixed text-on-primary-fixed">1</span>');
    });
});

describe('filter Tarif Pembayaran', function () {
    beforeEach(function () {
        $this->seed(SchoolDataSeeder::class);

        // Reproduksi kondisi asli bug: semua tarif berbagi created_at identik,
        // sehingga ORDER BY created_at DESC bersifat non-deterministik dan
        // pagination LIMIT/OFFSET melewatkan baris (level 10 hilang).
        PaymentRate::query()->update(['created_at' => '2026-08-15 05:01:28']);
    });

    it('Uang Buku + Semua Kelas menampilkan level -2, -1, 1, 10, 11, 12', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();

        $component = Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id);

        foreach ([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $level) {
            $rate = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', $level)->firstOrFail();
            $component->assertSee('payment-rate-'.$rate->id, false);
        }
    });

    it('Uang Buku + Semua Kelas menampilkan semua level jenis tersebut', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();

        $rates = PaymentRate::where('payment_type_id', $ub->id)->get();

        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->viewData('paymentRates')->items())
            ->pluck('class_level')
            ->values()
            ->all();

        expect($levels)->toBe([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])
            ->and($rates->count())->toBe(14);
    });

    it('Uang Buku + kelas 10 menampilkan hanya level 10', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();
        $rate10 = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', 10)->firstOrFail();

        $component = Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->set('filterClassLevel', '10')
            ->assertSee('payment-rate-'.$rate10->id, false);

        foreach ([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $level) {
            if ($level === 10) {
                continue;
            }

            $other = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', $level)->firstOrFail();
            $component->assertDontSee('payment-rate-'.$other->id, false);
        }
    });

    it('Uang Buku + kelas 9 menampilkan hanya level 9', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();
        $rate9 = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', 9)->firstOrFail();

        $component = Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->set('filterClassLevel', '9')
            ->assertSee('payment-rate-'.$rate9->id, false);

        foreach ([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $level) {
            if ($level === 9) {
                continue;
            }

            $other = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', $level)->firstOrFail();
            $component->assertDontSee('payment-rate-'.$other->id, false);
        }
    });

    it('mengosongkan filter kelas mengembalikan semua level', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();
        $rate10 = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', 10)->firstOrFail();

        $component = Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->set('filterClassLevel', '10')
            ->assertSee('payment-rate-'.$rate10->id, false);

        foreach ([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $level) {
            if ($level === 10) {
                continue;
            }

            $other = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', $level)->firstOrFail();
            $component->assertDontSee('payment-rate-'.$other->id, false);
        }

        Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->set('filterClassLevel', '')
            ->assertSee('payment-rate-'.$rate10->id, false);

        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->set('filterClassLevel', '')
            ->viewData('paymentRates')->items())
            ->pluck('class_level')
            ->values()
            ->all();

        expect($levels)->toBe([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
    });

    it('pagination tidak menyembunyikan level 10', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();
        $rate10 = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', 10)->firstOrFail();

        $component = Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id);

        $items = $component->viewData('paymentRates')->items();

        expect($items)->toHaveCount(14)
            ->and(collect($items)->pluck('id'))->toContain($rate10->id);

        $component->assertSee('payment-rate-'.$rate10->id, false);
    });

    it('filter kombinasi tanpa kelas tetap menampilkan level 10', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();
        $rate10 = PaymentRate::where('payment_type_id', $ub->id)->where('class_level', 10)->firstOrFail();

        Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->assertSee('payment-rate-'.$rate10->id, false);
    });

    it('jenis pembayaran lain (SPP) berperilaku konsisten', function () {
        $spp = PaymentType::where('name', 'SPP')->firstOrFail();

        $component = Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $spp->id);

        foreach ([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $level) {
            $rate = PaymentRate::where('payment_type_id', $spp->id)->where('class_level', $level)->firstOrFail();
            $component->assertSee('payment-rate-'.$rate->id, false);
        }
    });

    it('list tarif diurutkan berdasarkan level secara deterministik', function () {
        $ub = PaymentType::where('name', 'Uang Buku')->firstOrFail();

        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $ub->id)
            ->viewData('paymentRates')->items())
            ->pluck('class_level')
            ->values()
            ->all();

        expect($levels)->toBe([-2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
    });
});

describe('filter jenjang Tarif Pembayaran', function () {
    beforeEach(function () {
        $this->sppType = PaymentType::factory()->create(['name' => 'SPP Filter Jenjang']);
        $this->bookType = PaymentType::factory()->create(['name' => 'Uang Buku Filter Jenjang']);

        $this->rates = collect([
            'kb' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => -3]),
            'tkA' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => -2]),
            'tkB' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => -1]),
            'sd1' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => 1]),
            'sd6' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => 6]),
            'smp7' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => 7]),
            'smp8' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => 8]),
            'smp9Book' => PaymentRate::factory()->create(['payment_type_id' => $this->bookType->id, 'class_level' => 9]),
            'sma10' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => 10]),
            'sma12' => PaymentRate::factory()->create(['payment_type_id' => $this->sppType->id, 'class_level' => 12]),
        ]);
    });

    it('Semua Jenjang menampilkan semua tarif', function () {
        $ids = collect(Livewire::test(PaymentRateManagement::class)
            ->viewData('paymentRates')->items())
            ->pluck('id');

        expect($ids)->toContain(...$this->rates->pluck('id'));
    });

    it('filter TK hanya menampilkan tarif TK', function () {
        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'TK')
            ->viewData('paymentRates')->items())
            ->pluck('class_level')->unique()->values()->all();

        expect($levels)->toBe([-3, -2, -1]);
    });

    it('filter SD hanya menampilkan tarif SD', function () {
        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SD')
            ->viewData('paymentRates')->items())
            ->pluck('class_level')->unique()->values()->all();

        expect($levels)->toBe([1, 6]);
    });

    it('filter SMP hanya menampilkan tarif SMP', function () {
        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SMP')
            ->viewData('paymentRates')->items())
            ->pluck('class_level')->unique()->values()->all();

        expect($levels)->toBe([7, 8, 9]);
    });

    it('filter SMA hanya menampilkan tarif SMA', function () {
        $levels = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SMA')
            ->viewData('paymentRates')->items())
            ->pluck('class_level')->unique()->values()->all();

        expect($levels)->toBe([10, 12]);
    });

    it('filter jenjang bekerja bersama filter jenis pembayaran', function () {
        $ids = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SMP')
            ->set('filterPaymentTypeId', $this->sppType->id)
            ->viewData('paymentRates')->items())
            ->pluck('id')->values()->all();

        expect($ids)->toBe([$this->rates['smp7']->id, $this->rates['smp8']->id]);
    });

    it('filter jenjang bekerja bersama filter kelas', function () {
        $ids = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SMP')
            ->set('filterClassLevel', '8')
            ->viewData('paymentRates')->items())
            ->pluck('id')->values()->all();

        expect($ids)->toBe([$this->rates['smp8']->id]);
    });

    it('opsi kelas hanya memuat level dari jenjang terpilih', function () {
        Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SMP')
            ->assertSeeHtml('<option value="7">7</option>')
            ->assertSeeHtml('<option value="8">8</option>')
            ->assertSeeHtml('<option value="9">9</option>')
            ->assertDontSeeHtml('<option value="-3">KB</option>')
            ->assertDontSeeHtml('<option value="-2">TKA</option>')
            ->assertDontSeeHtml('<option value="1">1</option>')
            ->assertDontSeeHtml('<option value="10">10</option>');
    });

    it('mengganti jenjang mereset kelas yang tidak kompatibel', function () {
        Livewire::test(PaymentRateManagement::class)
            ->set('filterClassLevel', '10')
            ->set('filterSchoolLevel', 'SMP')
            ->assertSet('filterClassLevel', '');
    });

    it('filter jenis pembayaran tetap bekerja tanpa filter jenjang', function () {
        $ids = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterPaymentTypeId', $this->bookType->id)
            ->viewData('paymentRates')->items())
            ->pluck('id')->values()->all();

        expect($ids)->toBe([$this->rates['smp9Book']->id]);
    });

    it('filter kelas tetap bekerja tanpa filter jenjang', function () {
        $ids = collect(Livewire::test(PaymentRateManagement::class)
            ->set('filterClassLevel', '10')
            ->viewData('paymentRates')->items())
            ->pluck('id')->values()->all();

        expect($ids)->toBe([$this->rates['sma10']->id]);
    });

    it('filter tidak mengubah record PaymentRate', function () {
        $before = PaymentRate::query()->orderBy('id')->get()->toArray();

        Livewire::test(PaymentRateManagement::class)
            ->set('filterSchoolLevel', 'SMP')
            ->set('filterPaymentTypeId', $this->sppType->id)
            ->set('filterClassLevel', '8');

        expect(PaymentRate::query()->orderBy('id')->get()->toArray())->toBe($before);
    });
});

describe('format nominal Tarif Pembayaran', function () {
    it('edit memuat nominal dalam format ribuan Indonesia', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);
        $rate = PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 8,
            'amount' => 990000,
            'effective_from' => '2026-08-01',
        ]);

        Livewire::test(PaymentRateManagement::class)
            ->call('edit', $rate->id)
            ->assertSet('amount', '990.000');
    });

    it('edit memuat nominal jutaan dalam format ribuan Indonesia', function () {
        $type = PaymentType::factory()->create(['name' => 'Uang Pangkal']);
        $rate = PaymentRate::factory()->oneTime()->create([
            'payment_type_id' => $type->id,
            'class_level' => 7,
            'amount' => 2500000,
            'effective_from' => '2026-08-01',
        ]);

        Livewire::test(PaymentRateManagement::class)
            ->call('edit', $rate->id)
            ->assertSet('amount', '2.500.000');
    });

    it('menyimpan nominal dengan separator titik sebagai nilai numeric', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);

        Livewire::test(PaymentRateManagement::class)
            ->call('openModal')
            ->set('payment_type_id', $type->id)
            ->set('class_level', 7)
            ->set('amount', '1.000.000')
            ->set('billing_frequency', 'monthly')
            ->set('effective_from', '2026-08-01')
            ->call('save')
            ->assertHasNoErrors();

        $rate = PaymentRate::where('payment_type_id', $type->id)->where('class_level', 7)->first();

        expect($rate)->not->toBeNull()
            ->and($rate->amount)->toBe('1000000.00');
    });

    it('input nominal dirapikan menjadi format ribuan saat field ditinggalkan', function () {
        Livewire::test(PaymentRateManagement::class)
            ->set('amount', '990000')
            ->assertSet('amount', '990.000');
    });

    it('nominal negatif tetap ditolak setelah normalisasi separator', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);

        Livewire::test(PaymentRateManagement::class)
            ->call('openModal')
            ->set('payment_type_id', $type->id)
            ->set('class_level', 7)
            ->set('amount', '-5.000')
            ->set('billing_frequency', 'monthly')
            ->set('effective_from', '2026-08-01')
            ->call('save')
            ->assertHasErrors(['amount']);
    });

    it('edit lalu simpan dengan nominal berformat tidak mengubah nilai semestinya', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);
        $rate = PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 8,
            'amount' => 970000,
            'effective_from' => '2026-08-01',
        ]);

        Livewire::test(PaymentRateManagement::class)
            ->call('edit', $rate->id)
            ->assertSet('amount', '970.000')
            ->call('save')
            ->assertHasNoErrors();

        expect(PaymentRate::find($rate->id)->amount)->toBe('970000.00');
    });
});

describe('format nominal browser visible (rendered input value)', function () {
    it('edit DB 980000.00 merender value="980.000" langsung pada input', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);
        $rate = PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 7,
            'amount' => 980000,
            'effective_from' => '2026-08-01',
        ]);

        $component = Livewire::test(PaymentRateManagement::class)
            ->call('edit', $rate->id);

        expect($component->get('amount'))->toBe('980.000')
            ->and($component->html())->toContain('value="980.000"')
            ->and($component->html())->not->toContain('value="980000"')
            ->and($component->html())->not->toContain('value="980000.00"');
    });

    it('edit DB 1000000.00 merender value="1.000.000" langsung pada input', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);
        $rate = PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => 7,
            'amount' => 1000000,
            'effective_from' => '2026-08-01',
        ]);

        $component = Livewire::test(PaymentRateManagement::class)
            ->call('edit', $rate->id);

        expect($component->get('amount'))->toBe('1.000.000')
            ->and($component->html())->toContain('value="1.000.000"');
    });

    it('input/update 980000 menjadi 980.000 pada state komponen', function () {
        Livewire::test(PaymentRateManagement::class)
            ->set('amount', '980000')
            ->assertSet('amount', '980.000');
    });

    it('input/update 1000000 menjadi 1.000.000 pada state komponen', function () {
        Livewire::test(PaymentRateManagement::class)
            ->set('amount', '1000000')
            ->assertSet('amount', '1.000.000');
    });

    it('simpan 1.000.000 lalu buka kembali menampilkan 1.000.000', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);

        Livewire::test(PaymentRateManagement::class)
            ->call('openModal')
            ->set('payment_type_id', $type->id)
            ->set('class_level', 7)
            ->set('amount', '1.000.000')
            ->set('billing_frequency', 'monthly')
            ->set('effective_from', '2026-08-01')
            ->call('save')
            ->assertHasNoErrors();

        $rate = PaymentRate::where('payment_type_id', $type->id)->where('class_level', 7)->first();
        expect($rate)->not->toBeNull()
            ->and($rate->amount)->toBe('1000000.00');

        $reopened = Livewire::test(PaymentRateManagement::class)
            ->call('edit', $rate->id);

        expect($reopened->get('amount'))->toBe('1.000.000')
            ->and($reopened->html())->toContain('value="1.000.000"');
    });

    it('contoh nominal spesifikasi terformat dan cast decimal tidak bocor', function () {
        $instance = Livewire::test(PaymentRateManagement::class)->instance();

        expect($instance->formatAmountForDisplay(5000))->toBe('5.000')
            ->and($instance->formatAmountForDisplay(60000))->toBe('60.000')
            ->and($instance->formatAmountForDisplay(980000))->toBe('980.000')
            ->and($instance->formatAmountForDisplay(1000000))->toBe('1.000.000')
            ->and($instance->formatAmountForDisplay(10000000))->toBe('10.000.000')
            ->and($instance->formatAmountForDisplay('980000.00'))->toBe('980.000')
            ->and($instance->formatAmountForDisplay(null))->toBe('')
            ->and($instance->formatAmountForDisplay('abc'))->toBe('');
    });

    it('validasi gagal di field lain tetap menampilkan nominal berformat ulang', function () {
        $type = PaymentType::factory()->create(['name' => 'SPP']);

        $component = Livewire::test(PaymentRateManagement::class)
            ->call('openModal')
            ->set('payment_type_id', $type->id)
            ->set('class_level', 7)
            ->set('amount', '2.500.000')
            ->set('billing_frequency', 'monthly')
            ->set('effective_from', '2026-08-01')
            ->set('effective_until', '2026-07-01')
            ->call('save')
            ->assertHasErrors(['effective_until']);

        expect($component->get('amount'))->toBe('2.500.000');
    });

    it('tabel tarif dirender satu baris per kolom tanpa wrap', function () {
        $type = PaymentType::factory()->create(['name' => 'Uang Pangkal']);
        PaymentRate::factory()->oneTime()->create([
            'payment_type_id' => $type->id,
            'class_level' => 7,
            'amount' => 10000000,
            'effective_from' => '2026-08-01',
        ]);

        $html = Livewire::test(PaymentRateManagement::class)->html();

        expect($html)->toContain('<div class="overflow-x-auto">')
            ->and($html)->toContain('Rp 10.000.000')
            ->and($html)->toContain('py-4 px-6 text-body-md font-body-md text-on-surface whitespace-nowrap');
    });
});
