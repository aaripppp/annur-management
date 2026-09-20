<?php

namespace App\Livewire;

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
class DaycarePaymentEdit extends Component
{
    use WithFileUploads;

    public int $payment_id;

    public DaycareChild $child;

    public int $daycare_child_id;

    public string $receipt_number = '';

    /** @var array<int, array{description: string, amount: int|string}> */
    public array $items = [];

    /** @var array<int, string> */
    public array $itemKeys = [];

    public string $bank_id = '';

    public string $payment_date = '';

    public string $notes = '';

    public ?string $existingProof = null;

    public ?TemporaryUploadedFile $proof = null;

    public function mount(DaycarePayment $payment): void
    {
        $payment->load(['child', 'details']);

        $this->payment_id = $payment->id;
        $this->child = $payment->child;
        $this->daycare_child_id = $payment->daycare_child_id;
        $this->receipt_number = $payment->receipt_number;
        $this->bank_id = (string) $payment->bank_id;
        $this->payment_date = $payment->payment_date->format('Y-m-d');
        $this->notes = $payment->notes ?? '';
        $this->existingProof = $payment->proof_path;
        $this->items = $payment->details
            ->map(fn ($detail): array => [
                'description' => (string) $detail->description,
                'amount' => (int) round((float) $detail->amount),
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items = [['description' => '', 'amount' => '']];
        }

        $this->itemKeys = array_map(fn (): string => $this->newItemKey(), $this->items);
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'amount' => ''];
        $this->itemKeys[] = $this->newItemKey();
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) === 1 || ! array_key_exists($index, $this->items)) {
            return;
        }

        unset($this->items[$index], $this->itemKeys[$index]);
        $this->items = array_values($this->items);
        $this->itemKeys = array_values($this->itemKeys);
        $this->resetValidation();
    }

    public function totalAmount(): int
    {
        return collect($this->items)->sum(fn (array $item): int => max(0, (int) $this->normalizeAmount($item['amount'])));
    }

    public function formatAmount(mixed $amount): string
    {
        $normalized = $this->normalizeAmount($amount);

        return $normalized === '' ? '' : number_format((int) $normalized, 0, ',', '.');
    }

    public function save(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index] = [
                'description' => trim((string) $item['description']),
                'amount' => $this->normalizeAmount($item['amount']),
            ];
        }

        $validated = $this->validate([
            'payment_id' => ['required', 'integer', 'exists:daycare_payments,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'items.*.description.required' => 'Jenis pembayaran wajib diisi.',
            'items.*.amount.required' => 'Nominal pembayaran wajib diisi.',
            'items.*.amount.numeric' => 'Nominal pembayaran harus berupa angka.',
            'items.*.amount.gt' => 'Nominal pembayaran harus lebih dari 0.',
            'bank_id.required' => 'Bank penerima wajib dipilih.',
            'bank_id.exists' => 'Bank yang dipilih tidak valid atau tidak aktif.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'payment_date.date' => 'Tanggal pembayaran tidak valid.',
            'proof.mimes' => 'Bukti transfer harus berupa JPG, JPEG, PNG, atau PDF.',
            'proof.max' => 'Ukuran bukti transfer maksimal 5 MB.',
        ]);

        $items = collect($this->items)->map(fn (array $item): array => [
            'description' => trim((string) $item['description']),
            'amount' => (int) $item['amount'],
        ])->all();
        $totalAmount = collect($items)->sum('amount');
        $newProofPath = null;

        if ($this->proof !== null) {
            $newProofPath = $this->proof->store('daycare-payment-proofs', 'public');

            if ($newProofPath === false) {
                throw new RuntimeException('Bukti transfer Daycare gagal disimpan.');
            }
        }

        $oldProofPath = null;

        try {
            $payment = DB::transaction(function () use ($validated, $items, $totalAmount, $newProofPath, &$oldProofPath): DaycarePayment {
                $payment = DaycarePayment::query()->lockForUpdate()->find($this->payment_id);

                if ($payment === null || $payment->daycare_child_id !== $this->daycare_child_id) {
                    throw ValidationException::withMessages([
                        'payment_id' => 'Transaksi Daycare tidak valid.',
                    ]);
                }

                $oldProofPath = $payment->proof_path;
                $header = [
                    'bank_id' => (int) $validated['bank_id'],
                    'payment_date' => $validated['payment_date'],
                    'total_amount' => $totalAmount,
                    'notes' => filled($validated['notes']) ? trim((string) $validated['notes']) : null,
                ];

                if ($newProofPath !== null) {
                    $header['proof_path'] = $newProofPath;
                }

                $payment->update($header);
                $payment->details()->delete();
                $payment->details()->createMany($items);

                return $payment;
            });
        } catch (Throwable $exception) {
            if ($newProofPath !== null) {
                Storage::disk('public')->delete($newProofPath);
            }

            throw $exception;
        }

        if ($newProofPath !== null && $oldProofPath !== null && $oldProofPath !== $newProofPath) {
            Storage::disk('public')->delete($oldProofPath);
        }

        session()->flash('success', 'Pembayaran Daycare berhasil diperbarui.');
        $this->redirectRoute('daycare.payment.show', ['payment' => $payment]);
    }

    public function render(): mixed
    {
        $this->syncItemKeys();

        return view('livewire.daycare-payment-create', [
            'banks' => Bank::query()->where('is_active', true)->orderBy('name')->get(),
            'isEdit' => true,
            'existingProof' => $this->existingProof,
            'cancelUrl' => route('daycare.payment.show', $this->payment_id),
        ]);
    }

    private function normalizeAmount(mixed $amount): int|string
    {
        if (is_int($amount)) {
            return $amount;
        }

        $value = trim((string) $amount);
        $isNegative = str_starts_with($value, '-');
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return (int) $digits * ($isNegative ? -1 : 1);
    }

    private function syncItemKeys(): void
    {
        $this->itemKeys = array_slice(array_values($this->itemKeys), 0, count($this->items));

        while (count($this->itemKeys) < count($this->items)) {
            $this->itemKeys[] = $this->newItemKey();
        }
    }

    private function newItemKey(): string
    {
        return (string) Str::uuid();
    }
}
