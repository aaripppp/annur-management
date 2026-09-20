<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassPromotionRuleLog extends Model
{
    use HasFactory;

    /**
     * Audit trail entries are immutable; only created_at is maintained.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'source_class_id',
        'class_promotion_rule_id',
        'action_type',
        'old_action',
        'old_target_class_id',
        'new_action',
        'new_target_class_id',
        'changed_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function sourceClass()
    {
        return $this->belongsTo(SchoolClass::class, 'source_class_id');
    }

    public function rule()
    {
        return $this->belongsTo(ClassPromotionRule::class, 'class_promotion_rule_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
