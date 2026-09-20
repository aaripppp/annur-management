<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
    ];

    public function getSchoolLevelAttribute(): ?SchoolLevel
    {
        return SchoolLevel::fromClassLevel((int) $this->level);
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * Label tampilan untuk setiap level numerik.
     *
     * @return array<int, string>
     */
    public static function levelLabels(): array
    {
        return [
            -3 => 'KB',
            -2 => 'TKA',
            -1 => 'TKB',
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => '10',
            11 => '11',
            12 => '12',
        ];
    }

    /**
     * Opsi tingkat yang valid untuk form dropdown.
     *
     * @return array<int, string>
     */
    public static function levelOptions(): array
    {
        return self::levelLabels();
    }

    public function getLevelNameAttribute(): string
    {
        $labels = self::levelLabels();

        return $labels[$this->level] ?? "Tingkat {$this->level}";
    }
}
