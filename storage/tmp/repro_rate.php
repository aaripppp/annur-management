<?php
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Services\BillGenerationService;
use Illuminate\Support\Carbon;

$spp = PaymentType::where('name', 'SPP')->first();
echo "████ SEMUA payment_rates utk jenis SPP ████".PHP_EOL;
foreach (PaymentRate::where('payment_type_id', $spp->id)->orderBy('class_level')->orderBy('effective_from')->get() as $r) {
    echo "  level=".str_pad((string)$r->class_level,3)." amount=".str_pad((string)$r->amount,10)." eff_from=".$r->effective_from?->toDateString()." eff_until=".($r->effective_until?->toDateString() ?? '(null)')." freq=".($r->billing_frequency?->value ?? '-').PHP_EOL;
}
echo PHP_EOL."████ resolveRate utk level 7 tiap bulan ████".PHP_EOL;
$svc = app(BillGenerationService::class);
foreach (['2026-06-15','2026-07-15','2026-08-15','2026-09-15','2026-10-15'] as $d) {
    $r = $svc->resolveRate($spp, 7, Carbon::parse($d));
    echo "  ".$d." => ".($r ? "amount=".$r->amount." eff_from=".$r->effective_from?->toDateString() : "TIDAK ADA").PHP_EOL;
}
echo PHP_EOL."████ setting billbook.* ████".PHP_EOL;
foreach (App\Models\Setting::all() as $s) { if (str_contains($s->key, 'billbook')) echo "  ".$s->key." = ".json_encode($s->value).PHP_EOL; }
echo "████ AcademicYear aktif ████".PHP_EOL;
$y = App\Models\AcademicYear::active();
echo "  ".$y?->year." start=".$y?->start_date?->toDateString()." end=".$y?->end_date?->toDateString().PHP_EOL;
