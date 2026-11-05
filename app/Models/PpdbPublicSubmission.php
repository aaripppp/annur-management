<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpdbPublicSubmission extends Model
{
    protected $fillable = [
        'idempotency_key_hash',
        'success_reference_hash',
        'prospective_student_id',
        'consented_at',
        'consent_policy_version',
        'completed_at',
        'expires_at',
    ];

    protected $hidden = [
        'idempotency_key_hash',
        'success_reference_hash',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProspectiveStudent, $this> */
    public function prospectiveStudent(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudent::class);
    }

    public static function findCompletedByReference(string $reference): ?self
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $reference)) {
            return null;
        }

        return static::query()
            ->with(['prospectiveStudent.academicYear', 'prospectiveStudent.schoolClass'])
            ->where('success_reference_hash', hash('sha256', $reference))
            ->whereNotNull('completed_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
