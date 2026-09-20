<?php

namespace App\Models;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use Database\Factories\StudentEligibilityConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StudentEligibilityConfig extends Model
{
    /** @use HasFactory<StudentEligibilityConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'school_level',
        'default_pooled_payment_type_ids',
        'default_pooled_threshold',
        'default_pooled_is_active',
    ];

    protected $casts = [
        'school_level' => SchoolLevel::class,
        'default_pooled_payment_type_ids' => 'array',
        'default_pooled_threshold' => 'decimal:2',
        'default_pooled_is_active' => 'boolean',
    ];

    /** @return HasMany<StudentExamRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(StudentExamRequirement::class);
    }

    /** @return array<int, int> */
    public function canonicalPooledMemberIds(): array
    {
        return array_values(array_map(
            'intval',
            $this->default_pooled_payment_type_ids ?? [],
        ));
    }

    public function hasCanonicalPooledDefaults(): bool
    {
        return $this->canonicalPooledMemberIds() !== [];
    }

    public function defaultPooledThreshold(): float
    {
        return (float) ($this->default_pooled_threshold ?? 50);
    }

    public function defaultPooledActive(): bool
    {
        return (bool) ($this->default_pooled_is_active ?? false);
    }

    /**
     * Number of eligibility requirements that are currently active/selected.
     *
     * The badge beside each level only counts requirements with a persisted
     * `is_active = true` flag. Structural rows that exist merely for later
     * selection (for example an inactive pooled yearly requirement) never
     * count toward this number.
     */
    public function activeRequirementCount(): int
    {
        return $this->requirements()->where('is_active', true)->count();
    }

    /**
     * One-time baseline normalization: every persisted eligibility requirement
     * is marked inactive while its structural rows, pooled memberships and
     * thresholds are preserved. Rows are removed from evaluation and the
     * per-jenjang badge count, but they are never deleted. This is idempotent
     * and scoped strictly to eligibility configuration; no financial data is
     * touched.
     */
    public static function normalizeToEmptyBaseline(): void
    {
        StudentExamRequirement::query()->update(['is_active' => false]);
    }

    /**
     * Ensure every level reaches the canonical pooled defaults, creating the
     * structural pooled yearly requirement when it is missing and applicable.
     *
     * The canonical pair is resolved from an already-persisted pooled
     * requirement when one exists; otherwise it is resolved from the master
     * payment type names that represent the canonical Buku + Kegiatan pair.
     * This method is idempotent.
     */
    public static function initializeCanonicalDefaults(): void
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->orderBy('id')->first();
        $memberIds = self::canonicalPooledMemberIdsForInitialization();

        if ($activeYear === null || $memberIds === []) {
            return;
        }

        DB::transaction(function () use ($activeYear, $memberIds): void {
            foreach (SchoolLevel::cases() as $level) {
                if (! self::isPooledPairApplicable($level, $memberIds, $activeYear)) {
                    continue;
                }

                $config = self::query()->firstOrCreate(['school_level' => $level]);
                $config->default_pooled_payment_type_ids = $memberIds;
                $config->default_pooled_threshold = 50;
                $config->default_pooled_is_active = false;
                $config->save();

                self::cleanupIndividualYearlyMembers($config, $memberIds);

                $hasPooled = StudentExamRequirement::query()
                    ->where('student_eligibility_config_id', $config->id)
                    ->where('billing_frequency', BillFrequency::Yearly)
                    ->with('pooledPaymentTypes')
                    ->get()
                    ->contains(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2);

                if ($hasPooled) {
                    continue;
                }

                $pooled = StudentExamRequirement::query()->create([
                    'student_eligibility_config_id' => $config->id,
                    'school_level' => $level,
                    'payment_type_id' => $memberIds[0],
                    'billing_frequency' => BillFrequency::Yearly,
                    'required_percentage' => 50,
                    'is_active' => false,
                ]);
                $pooled->pooledPaymentTypes()->attach($memberIds);
            }
        });
    }

    /**
     * Restore this level's eligibility checker to an empty state.
     *
     * The structural pooled yearly requirement is always preserved (or
     * recreated), never deleted: its members, pivot entries, threshold and the
     * inactive default are kept. When stored canonical member ids are missing,
     * the pooled requirement's own members are treated as canonical. Every
     * other configured requirement for this level is removed and financial
     * data is never touched.
     */
    public function resetToCanonicalDefaults(): void
    {
        $configId = (int) $this->id;
        $level = $this->school_level;
        $defaultMemberIds = $this->canonicalPooledMemberIds();

        DB::transaction(function () use ($configId, $level, $defaultMemberIds): void {
            $existing = StudentExamRequirement::query()
                ->where('student_eligibility_config_id', $configId)
                ->with('pooledPaymentTypes')
                ->get();
            $existingPooled = $existing->first(fn (StudentExamRequirement $requirement): bool => $requirement->billing_frequency === BillFrequency::Yearly
                && $requirement->pooledPaymentTypes->count() >= 2);

            if ($existingPooled instanceof StudentExamRequirement) {
                $memberIds = $defaultMemberIds !== []
                    ? $defaultMemberIds
                    : $existingPooled->pooledPaymentTypes
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->sort()
                        ->values()
                        ->all();

                StudentExamRequirement::query()
                    ->where('student_eligibility_config_id', $configId)
                    ->whereKeyNot($existingPooled->id)
                    ->delete();

                $existingPooled->update([
                    'payment_type_id' => $memberIds[0] ?? (int) $existingPooled->payment_type_id,
                    'required_percentage' => (float) ($this->default_pooled_threshold ?? 50),
                    'is_active' => $this->defaultPooledActive(),
                ]);

                if ($memberIds !== []) {
                    $existingPooled->pooledPaymentTypes()->sync($memberIds);
                }

                return;
            }

            StudentExamRequirement::query()
                ->where('student_eligibility_config_id', $configId)
                ->delete();

            if ($defaultMemberIds === []) {
                return;
            }

            $pooled = StudentExamRequirement::query()->create([
                'student_eligibility_config_id' => $configId,
                'school_level' => $level,
                'payment_type_id' => $defaultMemberIds[0],
                'billing_frequency' => BillFrequency::Yearly,
                'required_percentage' => (float) ($this->default_pooled_threshold ?? 50),
                'is_active' => $this->defaultPooledActive(),
            ]);
            $pooled->pooledPaymentTypes()->sync($defaultMemberIds);
        });
    }

    /** @return array<int, int> */
    private static function canonicalPooledMemberIdsForInitialization(): array
    {
        $existingPooled = StudentExamRequirement::query()
            ->whereNotNull('student_eligibility_config_id')
            ->where('billing_frequency', BillFrequency::Yearly)
            ->with('pooledPaymentTypes')
            ->get()
            ->first(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2);

        if ($existingPooled instanceof StudentExamRequirement) {
            return $existingPooled->pooledPaymentTypes
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
        }

        $memberIds = PaymentType::query()
            ->whereIn('name', ['Uang Buku', 'Uang Kegiatan'])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return count($memberIds) === 2 ? $memberIds : [];
    }

    /** @param array<int, int> $memberIds */
    private static function isPooledPairApplicable(SchoolLevel $level, array $memberIds, AcademicYear $academicYear): bool
    {
        $classLevels = $level->classLevels();

        foreach ($memberIds as $memberId) {
            $hasMapping = PaymentTypeSchoolLevel::query()
                ->where('payment_type_id', $memberId)
                ->where('school_level', $level->value)
                ->where('is_active', true)
                ->exists();
            $hasRate = PaymentRate::query()
                ->where('payment_type_id', $memberId)
                ->whereIn('class_level', $classLevels)
                ->where('billing_frequency', BillFrequency::Yearly->value)
                ->whereDate('effective_from', '<=', $academicYear->end_date)
                ->where(fn ($query) => $query
                    ->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $academicYear->start_date))
                ->exists();

            if (! $hasMapping || ! $hasRate) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, int> $memberIds */
    private static function cleanupIndividualYearlyMembers(StudentEligibilityConfig $config, array $memberIds): void
    {
        $members = StudentExamRequirement::query()
            ->where('student_eligibility_config_id', $config->id)
            ->where('billing_frequency', BillFrequency::Yearly)
            ->whereIn('payment_type_id', $memberIds)
            ->with('pooledPaymentTypes')
            ->get();

        foreach ($members as $requirement) {
            if ($requirement->pooledPaymentTypes->count() >= 2) {
                continue;
            }

            $requirement->delete();
        }
    }
}
