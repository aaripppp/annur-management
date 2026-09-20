<?php

use App\Enums\BillFrequency;
use App\Models\PaymentRate;
use Illuminate\Support\Facades\DB;

it('backfill migration: rate bulanan existing tetap monthly', function () {
    $type = makeBillType('SPP', auto: true, required: true);

    DB::table('payment_rates')->insert([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 1750000,
        'is_monthly' => true,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('payment_rates')
        ->where('is_monthly', false)
        ->update(['billing_frequency' => 'one_time']);

    expect(DB::table('payment_rates')->where('is_monthly', true)->value('billing_frequency'))->toBe('monthly');
});

it('backfill migration: rate non-monthly existing menjadi one_time', function () {
    $type = makeBillType('Uang Pangkal', auto: false, required: false);

    DB::table('payment_rates')->insert([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => 5000000,
        'is_monthly' => false,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('payment_rates')
        ->where('is_monthly', false)
        ->update(['billing_frequency' => 'one_time']);

    expect(DB::table('payment_rates')->where('is_monthly', false)->value('billing_frequency'))->toBe('one_time');
});

it('frekuensi monthly tersimpan dan is_monthly tetap true', function () {
    $type = makeBillType('SPP', auto: true, required: true);

    $rate = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'billing_frequency' => BillFrequency::Monthly,
    ]);

    $fresh = PaymentRate::find($rate->id);

    expect($fresh->billing_frequency)->toBe(BillFrequency::Monthly)
        ->and($fresh->is_monthly)->toBeTrue()
        ->and((int) DB::table('payment_rates')->where('id', $rate->id)->value('is_monthly'))->toBe(1)
        ->and(DB::table('payment_rates')->where('id', $rate->id)->value('billing_frequency'))->toBe('monthly');
});

it('frekuensi yearly dapat disimpan', function () {
    $type = makeBillType('Uang Buku', auto: false, required: false);

    $rate = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'billing_frequency' => BillFrequency::Yearly,
    ]);

    $fresh = PaymentRate::find($rate->id);

    expect($fresh->billing_frequency)->toBe(BillFrequency::Yearly)
        ->and($fresh->is_monthly)->toBeFalse()
        ->and(DB::table('payment_rates')->where('id', $rate->id)->value('billing_frequency'))->toBe('yearly');
});

it('frekuensi one_time dapat disimpan', function () {
    $type = makeBillType('Uang Pangkal', auto: false, required: false);

    $rate = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    $fresh = PaymentRate::find($rate->id);

    expect($fresh->billing_frequency)->toBe(BillFrequency::OneTime)
        ->and($fresh->is_monthly)->toBeFalse()
        ->and(DB::table('payment_rates')->where('id', $rate->id)->value('billing_frequency'))->toBe('one_time');
});

it('kompatibilitas is_monthly: mass assignment is_monthly memetakan ke billing_frequency', function () {
    $type = makeBillType('OSIS', auto: false, required: false);

    $monthly = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'is_monthly' => true,
    ]);

    $oneTime = PaymentRate::factory()->create([
        'payment_type_id' => $type->id,
        'is_monthly' => false,
    ]);

    expect(PaymentRate::find($monthly->id)->billing_frequency)->toBe(BillFrequency::Monthly)
        ->and(PaymentRate::find($oneTime->id)->billing_frequency)->toBe(BillFrequency::OneTime);

    $oneTime->is_monthly = true;
    $oneTime->save();

    expect(PaymentRate::find($oneTime->id)->billing_frequency)->toBe(BillFrequency::Monthly)
        ->and(PaymentRate::find($oneTime->id)->is_monthly)->toBeTrue();
});

it('factory state yearly dan one_time tersedia', function () {
    $type = makeBillType('Uang Kegiatan', auto: false, required: false);

    $yearly = PaymentRate::factory()->yearly()->create(['payment_type_id' => $type->id]);
    $oneTime = PaymentRate::factory()->oneTime()->create(['payment_type_id' => $type->id]);

    expect($yearly->billing_frequency)->toBe(BillFrequency::Yearly)
        ->and($yearly->is_monthly)->toBeFalse()
        ->and($oneTime->billing_frequency)->toBe(BillFrequency::OneTime);
});
