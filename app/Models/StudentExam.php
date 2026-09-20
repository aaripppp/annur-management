<?php

namespace App\Models;

use Database\Factories\StudentExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentExam extends Model
{
    /** @use HasFactory<StudentExamFactory> */
    use HasFactory;

    protected $fillable = ['academic_year_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<StudentExamRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(StudentExamRequirement::class);
    }
}
