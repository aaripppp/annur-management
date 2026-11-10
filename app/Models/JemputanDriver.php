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

    protected $fillable = ['name', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<JemputanDriverAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(JemputanDriverAssignment::class);
    }
}
