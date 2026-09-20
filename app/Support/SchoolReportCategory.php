<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\StudentBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Helper kategori laporan sekolah (Harian & Bulanan).
 *
 * Memegang satu sumber kebenaran untuk:
 * - kategori pembayaran tagihan / manual
 * - normalisasi nama kategori
 * - kisi kategori yang aktif dikonfigurasi + hasil temuan data
 */
final class SchoolReportCategory
{
    public static function normalizedLabel(?string $value, string $fallback): string
    {
        $label = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($label) ? $label : $fallback;
    }

    public static function normalizationKey(string $value): string
    {
        return Str::lower(self::normalizedLabel($value, 'pembayaran'));
    }

    public static function categoryFor(Payment $payment, PaymentDetail $detail): string
    {
        if ($payment->isManualPayment()) {
            return self::normalizedLabel($detail->description, 'Pembayaran Manual');
        }

        $paymentType = $detail->getRelation('paymentType');

        if ($paymentType instanceof PaymentType) {
            return self::normalizedLabel($paymentType->name, 'Pembayaran');
        }

        $bill = $detail->getRelation('bill');
        $billPaymentType = $bill instanceof StudentBill
            ? $bill->getRelation('paymentType')
            : null;

        return self::normalizedLabel(
            $billPaymentType instanceof PaymentType ? $billPaymentType->name : null,
            'Pembayaran'
        );
    }

    public static function categoryForProspectiveDetail(ProspectiveStudentPaymentDetail $detail): string
    {
        $paymentType = $detail->getRelation('paymentType');

        if ($paymentType instanceof PaymentType) {
            return self::normalizedLabel($paymentType->name, 'Pembayaran');
        }

        $bill = $detail->getRelation('bill');
        $billPaymentType = $bill instanceof ProspectiveStudentBill
            ? $bill->getRelation('paymentType')
            : null;

        return self::normalizedLabel(
            $billPaymentType instanceof PaymentType ? $billPaymentType->name : null,
            'Pembayaran'
        );
    }

    public static function detailLabel(Payment $payment, PaymentDetail $detail, string $category): string
    {
        if ($payment->isManualPayment()) {
            return self::normalizedLabel($detail->description, 'Pembayaran Manual');
        }

        if ($detail->academic_year !== null) {
            return $category.' '.$detail->academic_year;
        }

        if ($detail->period_month !== null && $detail->period_year !== null) {
            $month = CarbonImmutable::create($detail->period_year, $detail->period_month, 1)
                ->settings(['locale' => 'id'])
                ->translatedFormat('F');

            return $category.' '.$month;
        }

        return $category;
    }

    /**
     * Kategori dari PaymentType aktif dalam urutan konfigurasi (id).
     *
     * @return list<array{key: string, name: string}>
     */
    public static function activeCategories(): array
    {
        $categories = [];

        foreach (PaymentType::query()->where('is_active', true)->orderBy('id')->pluck('name') as $name) {
            $label = self::normalizedLabel($name, 'Pembayaran');
            $key = self::normalizationKey($label);
            $categories[$key] ??= ['key' => $key, 'name' => $label];
        }

        return array_values($categories);
    }

    /**
     * Kisi kategori laporan: PaymentType aktif sesuai urutan konfigurasi,
     * disusul kategori temuan (misalnya deskripsi manual) yang belum terdaftar.
     *
     * @param  list<array{key: string, name: string}>  $discovered
     * @return list<array{key: string, name: string}>
     */
    public static function categoryGrid(array $discovered = []): array
    {
        $categories = [];

        foreach (self::activeCategories() as $category) {
            $categories[$category['key']] = $category;
        }

        foreach ($discovered as $category) {
            $categories[$category['key']] ??= $category;
        }

        return array_values($categories);
    }
}
