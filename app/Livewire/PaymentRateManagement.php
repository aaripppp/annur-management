<?php

namespace App\Livewire;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PaymentRateManagement extends Component
{
    use WithPagination;

    public $filterClassLevel = '';

    public $filterPaymentTypeId = '';

    public $filterSchoolLevel = '';

    public $isModalOpen = false;

    public $isEditing = false;

    public $isDeleteModalOpen = false;

    public $deletingId = null;

    public $rateId = null;

    public $payment_type_id = '';

    public $class_level = '';

    public $target_scope = 'class';

    public $school_level = '';

    public $amount = '';

    public $billing_frequency = 'monthly';

    public $effective_from = '';

    public $effective_until = '';

    public function updatedFilterClassLevel()
    {
        $this->resetPage();
    }

    public function updatedFilterPaymentTypeId()
    {
        $this->resetPage();
    }

    public function updatedFilterSchoolLevel(): void
    {
        $schoolLevel = SchoolLevel::tryFrom((string) $this->filterSchoolLevel);

        if ($schoolLevel !== null
            && $this->filterClassLevel !== ''
            && ! in_array((int) $this->filterClassLevel, $schoolLevel->classLevels(), true)) {
            $this->filterClassLevel = '';
        }

        $this->resetPage();
    }

    public function openModal()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['rateId', 'payment_type_id', 'class_level', 'school_level', 'amount', 'effective_from', 'effective_until', 'isEditing']);
        $this->target_scope = 'class';
        $this->billing_frequency = 'monthly';
        $this->resetValidation();
    }

    public function confirmDelete(int $id)
    {
        $this->deletingId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->isDeleteModalOpen = false;
    }

    public function edit(PaymentRate $rate)
    {
        $this->isEditing = true;
        $this->rateId = $rate->id;
        $this->payment_type_id = $rate->payment_type_id;
        $this->class_level = $rate->class_level;
        $this->target_scope = 'class';
        $this->school_level = '';
        $this->amount = $this->formatAmountForDisplay($rate->amount);
        $this->billing_frequency = $rate->billing_frequency?->value ?? ($rate->is_monthly ? 'monthly' : 'one_time');
        $this->effective_from = $rate->effective_from->format('Y-m-d');
        $this->effective_until = $rate->effective_until?->format('Y-m-d') ?? '';

        $this->isModalOpen = true;
    }

    /**
     * Tampilkan nominal dalam format Indonesia (990.000) pada input.
     *
     * @param  mixed  $value
     */
    public function formatAmountForDisplay($value): string
    {
        if ($value === null || $value === '' || ! is_numeric((string) $value)) {
            return '';
        }

        return number_format((float) $value, 0, ',', '.');
    }

    /**
     * Rapikan input nominal menjadi format ribuan saat field ditinggalkan
     * (wire:model.blur). Nilai yang belum valid dibiarkan apa adanya agar
     * tetap bisa dinilai oleh validasi saat save.
     */
    public function updatedAmount($value): void
    {
        $clean = str_replace('.', '', trim((string) $value));

        if (preg_match('/^\d+$/', $clean)) {
            $this->amount = $this->formatAmountForDisplay($clean);
        }
    }

    /**
     * Bersihkan separator titik sebelum validasi/simpan sehingga nilai
     * yang tersimpan di database tetap numeric murni.
     */
    private function normalizeAmountInput(): void
    {
        $this->amount = str_replace('.', '', trim((string) $this->amount));
    }

    public function save()
    {
        $this->normalizeAmountInput();

        try {
            $validatedData = $this->validate([
                'payment_type_id' => 'required|exists:payment_types,id',
                'target_scope' => 'required|in:class,school_level',
                'class_level' => 'required_if:target_scope,class|nullable|integer|in:-3,-2,-1,1,2,3,4,5,6,7,8,9,10,11,12',
                'school_level' => 'required_if:target_scope,school_level|nullable|in:TK,SD,SMP,SMA',
                'amount' => 'required|numeric|min:0',
                'billing_frequency' => 'required|in:monthly,yearly,one_time',
                'effective_from' => 'required|date',
                'effective_until' => 'nullable|date|after_or_equal:effective_from',
            ]);
        } catch (ValidationException $exception) {
            // Modal tetap terbuka saat validasi gagal; kembalikan format
            // ribuan agar input tidak menampilkan digit mentah.
            if (preg_match('/^\d+$/', $this->amount)) {
                $this->amount = $this->formatAmountForDisplay($this->amount);
            }

            throw $exception;
        }

        if (empty($validatedData['effective_until'])) {
            $validatedData['effective_until'] = null;
        }

        $rateData = [
            'payment_type_id' => $validatedData['payment_type_id'],
            'class_level' => $validatedData['class_level'],
            'amount' => $validatedData['amount'],
            'billing_frequency' => $validatedData['billing_frequency'],
            'effective_from' => $validatedData['effective_from'],
            'effective_until' => $validatedData['effective_until'],
        ];

        if ($this->isEditing) {
            PaymentRate::query()->findOrFail((int) $this->rateId)->update($rateData);
            session()->flash('success', 'Tarif pembayaran berhasil diupdate.');
        } elseif ($validatedData['target_scope'] === 'school_level') {
            $schoolLevel = SchoolLevel::from($validatedData['school_level']);
            $effectiveFrom = Carbon::parse($validatedData['effective_from'])->startOfDay();

            DB::transaction(function () use ($validatedData, $schoolLevel, $effectiveFrom): void {
                foreach ($schoolLevel->classLevels() as $classLevel) {
                    PaymentRate::query()->updateOrCreate(
                        [
                            'payment_type_id' => $validatedData['payment_type_id'],
                            'class_level' => $classLevel,
                            'effective_from' => $effectiveFrom,
                        ],
                        [
                            'amount' => $validatedData['amount'],
                            'billing_frequency' => $validatedData['billing_frequency'],
                            'effective_until' => $validatedData['effective_until'],
                        ]
                    );
                }
            });
            session()->flash('success', 'Tarif pembayaran jenjang '.$schoolLevel->value.' berhasil disimpan.');
        } else {
            PaymentRate::create($rateData);
            session()->flash('success', 'Tarif pembayaran baru berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function messages()
    {
        return [
            'payment_type_id.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_type_id.exists' => 'Jenis pembayaran yang dipilih tidak valid.',
            'class_level.required' => 'Tingkat kelas wajib dipilih.',
            'school_level.required_if' => 'Jenjang wajib dipilih.',
            'class_level.integer' => 'Tingkat kelas harus berupa angka.',
            'class_level.in' => 'Tingkat kelas yang dipilih tidak valid.',
            'amount.required' => 'Nominal tarif wajib diisi.',
            'amount.numeric' => 'Nominal tarif harus berupa angka.',
            'amount.min' => 'Nominal tarif tidak boleh negatif.',
            'billing_frequency.required' => 'Periode tagihan wajib dipilih.',
            'billing_frequency.in' => 'Periode tagihan yang dipilih tidak valid.',
            'effective_from.required' => 'Tanggal berlaku wajib diisi.',
            'effective_from.date' => 'Format tanggal berlaku tidak valid.',
            'effective_until.date' => 'Format tanggal berakhir tidak valid.',
            'effective_until.after_or_equal' => 'Tanggal berakhir harus setelah atau sama dengan tanggal berlaku.',
        ];
    }

    public function delete()
    {
        if (! $this->deletingId) {
            return;
        }

        PaymentRate::findOrFail($this->deletingId)->delete();
        $this->cancelDelete();
        session()->flash('success', 'Tarif pembayaran berhasil dihapus.');
    }

    public function frequencyLabel(?BillFrequency $frequency): string
    {
        return match ($frequency) {
            BillFrequency::Yearly => 'Tahunan',
            BillFrequency::OneTime => 'Sekali Bayar',
            default => 'Bulanan',
        };
    }

    public function render()
    {
        // Urutkan secara deterministik berdasarkan class_level + id. `latest()`
        // (created_at DESC) tanpa tiebreaker menghasilkan urutan non-deterministik
        // ketika banyak record berbagi created_at yang sama; dipadukan dengan
        // pagination LIMIT/OFFSET hal ini membuat baris tertentu (mis. level 10)
        // terlewat dari SEMUA halaman dan baris lain muncul dua kali.
        $query = PaymentRate::with('paymentType')
            ->orderBy('class_level')
            ->orderBy('id');

        if ($this->filterClassLevel !== '') {
            $query->where('class_level', $this->filterClassLevel);
        }

        if ($this->filterPaymentTypeId !== '') {
            $query->where('payment_type_id', $this->filterPaymentTypeId);
        }

        $selectedSchoolLevel = SchoolLevel::tryFrom((string) $this->filterSchoolLevel);

        if ($selectedSchoolLevel !== null) {
            $query->whereIn('class_level', $selectedSchoolLevel->classLevels());
        }

        $classLevelOptions = SchoolClass::levelLabels();

        if ($selectedSchoolLevel !== null) {
            $classLevelOptions = array_intersect_key(
                $classLevelOptions,
                array_flip($selectedSchoolLevel->classLevels())
            );
        }

        return view('livewire.payment-rate.index', [
            // Page size harus menampung seluruh level valid (-3 hingga 12) dari satu
            // jenis pembayaran agar tidak ada level yang "hilang" di halaman 1.
            'paymentRates' => $query->paginate(25),
            'totalRates' => PaymentRate::count(),
            'paymentTypes' => PaymentType::orderBy('name')->get(),
            'schoolLevelOptions' => SchoolLevel::cases(),
            'classLevelOptions' => $classLevelOptions,
        ]);
    }
}
