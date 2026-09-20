<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExamRequirement;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PaymentTypeManagement extends Component
{
    use WithPagination;

    public $isModalOpen = false;

    public $isEditing = false;

    public $isDeleteModalOpen = false;

    public $deletingId = null;

    public $paymentTypeId = null;

    public $name = '';

    public $audience = 'student';

    public $is_active = true;

    /** @var array<int, string> */
    public array $schoolLevels = [];

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
        $this->reset(['paymentTypeId', 'name', 'audience', 'schoolLevels', 'isEditing']);
        $this->is_active = true;
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

    public function edit(PaymentType $paymentType)
    {
        $this->isEditing = true;
        $this->paymentTypeId = $paymentType->id;
        $this->name = $paymentType->name;
        $this->audience = $paymentType->audience?->value ?? 'student';
        $this->is_active = $paymentType->is_active;
        $this->schoolLevels = $paymentType->paymentTypeSchoolLevels()
            ->where('is_active', true)
            ->pluck('school_level')
            ->map(fn (SchoolLevel|string $level): string => $level instanceof SchoolLevel ? $level->value : $level)
            ->all();

        $this->isModalOpen = true;
    }

    public function save()
    {
        $validatedData = $this->validate([
            'name' => 'required|string|max:100',
            'audience' => 'required|in:student,prospective_student',
            'is_active' => 'boolean',
            'schoolLevels' => 'array',
            'schoolLevels.*' => 'in:TK,SD,SMP,SMA',
        ]);

        DB::transaction(function () use ($validatedData): void {
            $typeData = [
                'name' => $validatedData['name'],
                'audience' => $validatedData['audience'],
                'is_active' => $validatedData['is_active'],
            ];
            $paymentType = $this->isEditing
                ? PaymentType::query()->findOrFail((int) $this->paymentTypeId)
                : new PaymentType;

            $paymentType->fill($typeData)->save();

            foreach (SchoolLevel::cases() as $schoolLevel) {
                $mapping = PaymentTypeSchoolLevel::query()
                    ->where('payment_type_id', $paymentType->id)
                    ->where('school_level', $schoolLevel)
                    ->first();
                $isApplicable = in_array($schoolLevel->value, $validatedData['schoolLevels'], true);

                if ($mapping) {
                    $mapping->update(['is_active' => $isApplicable]);
                } elseif ($isApplicable) {
                    PaymentTypeSchoolLevel::create([
                        'payment_type_id' => $paymentType->id,
                        'school_level' => $schoolLevel,
                        'is_required' => $paymentType->is_required ?? false,
                        'is_active' => true,
                    ]);
                }
            }
        });

        session()->flash('success', $this->isEditing
            ? 'Jenis pembayaran berhasil diupdate.'
            : 'Jenis pembayaran baru berhasil ditambahkan.');

        $this->closeModal();
    }

    public function messages()
    {
        return [
            'name.required' => 'Nama jenis pembayaran wajib diisi.',
            'name.max' => 'Nama jenis pembayaran maksimal 100 karakter.',
        ];
    }

    public function delete()
    {
        if (! $this->deletingId) {
            return;
        }

        $type = PaymentType::withCount(['paymentDetails', 'rates', 'bills', 'studentSettings', 'prospectiveBills'])
            ->findOrFail($this->deletingId);
        $blockers = [];

        if ($type->payment_details_count > 0) {
            $blockers[] = "{$type->payment_details_count} rincian transaksi pembayaran";
        }

        if ($type->rates_count > 0) {
            $blockers[] = "{$type->rates_count} tarif pembayaran";
        }

        if ($type->bills_count > 0) {
            $blockers[] = "{$type->bills_count} tagihan siswa";
        }

        if ($type->student_settings_count > 0) {
            $blockers[] = "{$type->student_settings_count} pengaturan pembayaran siswa";
        }

        if ($type->prospective_bills_count > 0) {
            $blockers[] = "{$type->prospective_bills_count} tagihan pendaftaran calon siswa";
        }

        $type->paymentTypeSchoolLevels()
            ->where('is_active', true)
            ->pluck('school_level')
            ->each(function (SchoolLevel|string $schoolLevel) use (&$blockers): void {
                $label = $schoolLevel instanceof SchoolLevel ? $schoolLevel->value : $schoolLevel;
                $blockers[] = "Konfigurasi jenjang {$label}";
            });

        $eligibilityLevels = StudentExamRequirement::query()
            ->where(function ($query) use ($type): void {
                $query->where('payment_type_id', $type->id)
                    ->orWhereHas('pooledPaymentTypes', fn ($paymentTypeQuery) => $paymentTypeQuery->whereKey($type->id));
            })
            ->pluck('school_level')
            ->map(fn (SchoolLevel|string $schoolLevel): string => $schoolLevel instanceof SchoolLevel ? $schoolLevel->value : $schoolLevel);

        StudentEligibilityConfig::query()
            ->get()
            ->filter(fn (StudentEligibilityConfig $config): bool => in_array($type->id, $config->canonicalPooledMemberIds(), true))
            ->each(fn (StudentEligibilityConfig $config) => $eligibilityLevels->push($config->school_level->value));

        $eligibilityLevels->unique()->sort()->each(function (string $schoolLevel) use (&$blockers): void {
            $blockers[] = "Kriteria Kelayakan {$schoolLevel}";
        });

        if ($blockers !== []) {
            $this->cancelDelete();
            session()->flash('error', 'Jenis pembayaran masih digunakan oleh: '.implode(', ', $blockers).'.');

            return;
        }

        DB::transaction(function () use ($type): void {
            $type->paymentTypeSchoolLevels()->where('is_active', false)->delete();
            $type->delete();
        });
        $this->cancelDelete();
        session()->flash('success', 'Jenis pembayaran berhasil dihapus.');
    }

    public function render()
    {
        return view('livewire.payment-type.index', [
            'paymentTypes' => PaymentType::query()
                ->with(['paymentTypeSchoolLevels', 'rates'])
                ->latest()
                ->paginate(10),
            'totalTypes' => PaymentType::count(),
            'schoolLevelOptions' => SchoolLevel::cases(),
        ]);
    }
}
