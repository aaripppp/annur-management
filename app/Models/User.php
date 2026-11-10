<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SchoolLevel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property SchoolLevel|null $school_level
 * @property bool $is_active
 * @property string|null $position
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'school_level', 'is_active', 'position'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TU_UNIT = 'tu_unit';

    public const POSITION_DIRECTOR = 'Direktur Keuangan';

    public const POSITION_HEAD_TU_FOUNDATION = 'Kepala TU Yayasan';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'school_level',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'school_level' => SchoolLevel::class,
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTuUnit(): bool
    {
        return $this->role === self::ROLE_TU_UNIT;
    }

    public function hasSchoolLevel(SchoolLevel $schoolLevel): bool
    {
        return $this->school_level === $schoolLevel;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasReservedPosition(): bool
    {
        return in_array($this->position, [
            self::POSITION_DIRECTOR,
            self::POSITION_HEAD_TU_FOUNDATION,
        ], true);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_TU_UNIT => 'TU Unit',
            self::ROLE_ADMIN => 'Admin',
            default => 'Tidak Dikenal',
        };
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }
}
