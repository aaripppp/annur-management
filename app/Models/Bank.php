<?php

namespace App\Models;

use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory;

    public const TYPE_BANK = 'bank';

    public const TYPE_CASH = 'cash';

    protected $attributes = [
        'type' => self::TYPE_BANK,
    ];

    protected $fillable = [
        'name',
        'type',
        'account_number',
        'account_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return array<int, string> */
    public static function types(): array
    {
        return [self::TYPE_BANK, self::TYPE_CASH];
    }

    public function isBank(): bool
    {
        return $this->type === self::TYPE_BANK;
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }

    public function typeLabel(): string
    {
        return $this->isCash() ? 'Tunai / Cash' : 'Rekening Bank';
    }

    public function reportingTypeLabel(): string
    {
        return $this->isCash() ? 'TUNAI / CASH' : 'TRANSFER / DEBET';
    }

    public function paymentLabel(): string
    {
        return $this->isCash() ? 'Tunai' : $this->name;
    }

    public function displayLabel(): string
    {
        $accountNumber = $this->displayAccountNumber();

        return $accountNumber === null
            ? $this->paymentLabel()
            : $this->name.' — '.$accountNumber;
    }

    public function displayAccountNumber(): ?string
    {
        if ($this->isCash()) {
            return null;
        }

        $accountNumber = trim((string) $this->account_number);

        return $accountNumber !== '' ? $accountNumber : null;
    }

    public function optionLabel(): string
    {
        return $this->displayLabel();
    }

    public function accountSummary(): ?string
    {
        if ($this->isCash()) {
            return null;
        }

        return collect([$this->account_name, $this->account_number])
            ->filter(fn (?string $value): bool => filled($value))
            ->implode(' · ');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
