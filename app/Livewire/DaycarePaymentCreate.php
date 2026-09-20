<?php

namespace App\Livewire;

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Services\DaycareReceiptNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
class DaycarePaymentCreate extends Component
{
    use WithFileUploads;

    public DaycareChild $child;

    public int $daycare_child_id;

    /** @var array<int, array{description: string, amount: int|string}> */
    public array $items = [];

    /** @var array<int, string> */
    public array $itemKeys = [];

    public string $bank_id = '';

    public string $payment_date = '';

    public string $notes = '';

    public ?TemporaryUploadedFile $proof = null;

    public function mount(DaycareChild $child): void
    {
        $this->child = $child;
        $this->daycare_child_id = $child->id;
        $this->payment_date = now()->format('Y-m-d');
        $this->bank_id = (string) (Bank::query()->where('is_active', true)->orderBy('name')->value('id') ?? '');
        $this->items = [['description' => '', 'amount' => '']];
        $this->itemKeys = [$this->newItemKey()];
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

        unset($this->items[$index]);
        unset($this->itemKeys[$index]);
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

    public function save(DaycareReceiptNumberGenerator $receiptNumberGenerator): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['amount'] = $this->normalizeAmount($item['amount']);
        }

        $validated = $this->validate([
            'daycare_child_id' => ['required', 'integer', 'exists:daycare_children,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
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

        $items = [];
        foreach ($this->items as $item) {
            $items[] = [
                'description' => trim((string) $item['description']),
                'amount' => (int) $item['amount'],
            ];
        }

        $totalAmount = collect($items)->sum('amount');
        $proofPath = null;

        if ($this->proof !== null) {
            $proofPath = $this->proof->store('daycare-payment-proofs', 'public');

            if ($proofPath === false) {
                throw new RuntimeException('Bukti transfer Daycare gagal disimpan.');
            }
        }

        try {
            $payment = DB::transaction(function () use ($validated, $items, $totalAmount, $proofPath, $receiptNumberGenerator): DaycarePayment {
                $paymentYear = Carbon::parse($validated['payment_date'])->year;
                $payment = DaycarePayment::query()->create([
                    'receipt_number' => $receiptNumberGenerator->next($paymentYear),
                    'daycare_child_id' => (int) $validated['daycare_child_id'],
                    'bank_id' => (int) $validated['bank_id'],
                    'payment_date' => $validated['payment_date'],
                    'total_amount' => $totalAmount,
                    'proof_path' => $proofPath,
                    'notes' => filled($validated['notes']) ? trim((string) $validated['notes']) : null,
                    'created_by' => auth()->id(),
                ]);

                $payment->details()->createMany($items);

                return $payment;
            });
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('public')->delete($proofPath);
            }

            throw $exception;
        }

        session()->flash('success', 'Pembayaran Daycare berhasil dicatat.');
        $this->redirectRoute('daycare.payment.show', ['payment' => $payment]);
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

    public function render(): mixed
    {
        $this->syncItemKeys();

        return view('livewire.daycare-payment-create', [
            'banks' => Bank::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
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
