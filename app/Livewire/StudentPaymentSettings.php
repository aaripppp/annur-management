<?php

namespace App\Livewire;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\Student;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class StudentPaymentSettings extends Component
{
    public Student $student;

    /** @var array<int, array{payment_type_id:int, name:string, is_required:bool, is_active:bool, started_at:CarbonInterface|null, ended_at:CarbonInterface|null, frequency:string|null, default_amount:float|null, custom_amount:float|null}> */
    public array $settings = [];

    /** @var array<int, string> keyed by payment_type_id */
    public array $customAmountInputs = [];

    public ?int $activatePaymentTypeId = null;

    public string $activateTypeName = '';

    public string $activateStartMonth = '';

    public bool $isActivateOpen = false;

    public bool $isEditingStartDate = false;

    public function mount(Student $student): void
    {
        $this->student = $student;
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $types = PaymentType::where('audience', PaymentTypeAudience::Student)->orderBy('name')->get();

        $existing = $this->student->paymentSettings()
            ->get()
            ->keyBy('payment_type_id');

        $level = $this->student->schoolClass?->level;

        $schoolLevel = $this->student->schoolLevel;

        $levelDefaults = $schoolLevel !== null
            ? PaymentTypeSchoolLevel::query()
                ->where('school_level', $schoolLevel)
                ->pluck('is_required', 'payment_type_id')
            : collect();

        $this->settings = $types->map(function (PaymentType $type) use ($existing, $level, $levelDefaults) {
            $rate = $this->latestRateFor($type, $level);
            $setting = $existing->get($type->id);

            return [
                'payment_type_id' => $type->id,
                'name' => $type->name,
                'is_required' => (bool) ($levelDefaults[$type->id] ?? false),
                'is_active' => (bool) ($setting->is_active ?? false),
                'started_at' => $setting?->started_at,
                'ended_at' => $setting?->ended_at,
                'frequency' => $this->frequencyLabel($rate?->billing_frequency),
                'default_amount' => $rate ? (float) $rate->amount : null,
                'custom_amount' => $setting?->custom_amount !== null ? (float) $setting->custom_amount : null,
            ];
        })
            ->sortByDesc('is_required')
            ->values()
            ->all();

        $this->customAmountInputs = [];

        foreach ($this->settings as $row) {
            $this->customAmountInputs[$row['payment_type_id']] = $row['custom_amount'] !== null
                ? (string) $row['custom_amount']
                : '';
        }
    }

    /**
     * Rate default terbaru untuk sebuah tipe pada level siswa (untuk tampilan).
     */
    protected function latestRateFor(PaymentType $type, ?int $level): ?PaymentRate
    {
        if ($level === null) {
            return null;
        }

        return PaymentRate::query()
            ->where('payment_type_id', $type->id)
            ->where('class_level', $level)
            ->latest('effective_from')
            ->latest('id')
            ->first();
    }

    protected function frequencyLabel(?BillFrequency $frequency): ?string
    {
        return match ($frequency) {
            BillFrequency::Monthly => 'Bulanan',
            BillFrequency::Yearly => 'Tahunan',
            BillFrequency::OneTime => 'Sekali Bayar',
            default => null,
        };
    }

    public function toggle(int $paymentTypeId): void
    {
        $row = collect($this->settings)->firstWhere('payment_type_id', $paymentTypeId);

        if (! $row) {
            return;
        }

        if ($row['is_required']) {
            session()->flash('error', 'Jenis pembayaran wajib untuk jenjang ini tidak bisa dinonaktifkan.');
            $this->loadSettings();

            return;
        }

        if (! $row['is_active']) {
            $this->confirmActivate($paymentTypeId);

            return;
        }

        StudentPaymentSetting::where('student_id', $this->student->id)
            ->where('payment_type_id', $paymentTypeId)
            ->update(['is_active' => false]);

        $this->loadSettings();

        $this->dispatch('student-payment-settings-updated', studentId: $this->student->id);

        session()->flash('success', 'Keikutsertaan pembayaran berhasil dinonaktifkan.');
    }

    /**
     * Simpan (atau hapus) nominal khusus untuk satu tipe pembayaran opsional.
     *
     * Kosong -> custom_amount NULL (kembali ke tarif default). Nominal ini
     * hanyalah referensi default; tagihan opsional dibuat manual dari Buku
     * Tagihan dan memakai nominal yang dimasukkan admin saat itu.
     */
    public function saveCustomAmount(int $paymentTypeId): void
    {
        $row = collect($this->settings)->firstWhere('payment_type_id', $paymentTypeId);

        if (! $row || $row['is_required']) {
            session()->flash('error', 'Nominal khusus tidak berlaku untuk jenis pembayaran wajib.');

            return;
        }

        $value = $this->customAmountInputs[$paymentTypeId] ?? null;

        if ($value === null || trim((string) $value) === '') {
            $customAmount = null;
        } else {
            $this->validate([
                "customAmountInputs.$paymentTypeId" => ['numeric', 'gt:0'],
            ], [
                "customAmountInputs.$paymentTypeId.numeric" => 'Nominal khusus harus berupa angka.',
                "customAmountInputs.$paymentTypeId.gt" => 'Nominal khusus harus lebih besar dari 0.',
            ]);

            $customAmount = (float) $value;
        }

        StudentPaymentSetting::updateOrCreate(
            ['student_id' => $this->student->id, 'payment_type_id' => $paymentTypeId],
            ['custom_amount' => $customAmount]
        );

        $this->customAmountInputs[$paymentTypeId] = $customAmount === null ? '' : (string) $customAmount;

        $this->loadSettings();

        $this->dispatch('student-payment-settings-updated', studentId: $this->student->id);

        session()->flash('success', $customAmount === null
            ? 'Nominal khusus '.$row['name'].' dihapus — kembali memakai tarif default.'
            : 'Nominal khusus '.$row['name'].' disimpan: Rp '.number_format($customAmount, 0, ',', '.').'.');
    }

    public function confirmActivate(int $paymentTypeId): void
    {
        $row = collect($this->settings)->firstWhere('payment_type_id', $paymentTypeId);

        if (! $row || $row['is_required']) {
            session()->flash('error', 'Jenis pembayaran wajib untuk jenjang ini tidak bisa diubah awal berlakunya.');
            $this->loadSettings();

            return;
        }

        $this->activatePaymentTypeId = $paymentTypeId;
        $this->activateTypeName = $row['name'];
        $this->isEditingStartDate = false;
        $this->activateStartMonth = now()->format('Y-m');
        $this->isActivateOpen = true;
    }

    public function editStartDate(int $paymentTypeId): void
    {
        $row = collect($this->settings)->firstWhere('payment_type_id', $paymentTypeId);

        if (! $row || $row['is_required'] || ! $row['is_active']) {
            return;
        }

        $setting = $this->student->paymentSettings()
            ->where('payment_type_id', $paymentTypeId)
            ->first();

        $this->activatePaymentTypeId = $paymentTypeId;
        $this->activateTypeName = $row['name'];
        $this->isEditingStartDate = true;
        $this->activateStartMonth = $setting?->started_at?->format('Y-m') ?? now()->format('Y-m');
        $this->isActivateOpen = true;
    }

    public function closeActivate(): void
    {
        $this->reset('activatePaymentTypeId', 'activateTypeName', 'activateStartMonth', 'isActivateOpen', 'isEditingStartDate');
    }

    public function activate(): void
    {
        if (! $this->activatePaymentTypeId) {
            return;
        }

        $row = collect($this->settings)->firstWhere('payment_type_id', $this->activatePaymentTypeId);

        if (! $row || $row['is_required']) {
            session()->flash('error', 'Jenis pembayaran wajib untuk jenjang ini tidak bisa diubah awal berlakunya.');
            $this->closeActivate();

            return;
        }

        $this->validate([
            'activateStartMonth' => ['required', 'date_format:Y-m'],
        ]);

        $startDate = Carbon::createFromFormat('Y-m', $this->activateStartMonth)->startOfMonth();

        $isEditing = $this->isEditingStartDate;
        $typeName = $this->activateTypeName;

        StudentPaymentSetting::updateOrCreate(
            ['student_id' => $this->student->id, 'payment_type_id' => $this->activatePaymentTypeId],
            ['is_active' => true, 'started_at' => $startDate->toDateString(), 'ended_at' => null]
        );

        $this->closeActivate();
        $this->loadSettings();

        $this->dispatch('student-payment-settings-updated', studentId: $this->student->id);

        $message = $isEditing
            ? 'Mulai berlaku '.$typeName.' berhasil diubah ke '.$startDate->translatedFormat('F Y').'.'
            : 'Keikutsertaan '.$typeName.' berhasil diaktifkan mulai '.$startDate->translatedFormat('F Y').'.';

        session()->flash('success', $message);
    }

    public function generateBills(): void
    {
        $created = app(BillGenerationService::class)->generateForStudent($this->student);

        if (count($created) > 0) {
            session()->flash('success', count($created).' tagihan berhasil dibuat untuk periode '.now()->translatedFormat('F Y').'.');
        } else {
            session()->flash('info', 'Tidak ada tagihan baru — semua tagihan untuk periode '.now()->translatedFormat('F Y').' sudah tersedia.');
        }
    }

    public function render(): View
    {
        return view('livewire.student.payment-settings');
    }
}
