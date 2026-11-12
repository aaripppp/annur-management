<?php

namespace App\Models;

use Database\Factories\JemputanDriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JemputanDriver extends Model
{
    /** @use HasFactory<JemputanDriverFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'foundation_deduction_percentage', 'is_active'];

    protected function casts(): array
    {
        return [
            'foundation_deduction_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<JemputanDriverAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(JemputanDriverAssignment::class);
    }

    /** @return HasMany<JemputanDriverSalaryAllocation, $this> */
    public function salaryAllocations(): HasMany
    {
        return $this->hasMany(JemputanDriverSalaryAllocation::class);
    }

    /** @return HasMany<JemputanDriverDeductionRate, $this> */
    public function deductionRates(): HasMany
    {
        return $this->hasMany(JemputanDriverDeductionRate::class);
    }
}
