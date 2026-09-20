<?php

namespace App\Support;

use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;

class ClassPromotionMapping
{
    /**
     * Reason identifiers for blocked promotion decisions.
     */
    public const REASON_MISSING_RULE = 'missing_rule';

    public const REASON_INACTIVE_RULE = 'inactive_rule';

    public const REASON_INVALID_TARGET = 'invalid_target';

    /**
     * Resolve the runtime promotion decision for a source class.
     *
     * The class_promotion_rules table is the sole source of truth at runtime
     * (preview, preflight and execution): an active rule wins, a missing or
     * inactive rule blocks the promotion. There is NO implicit fallback — a
     * source class without a configured rule can never promote or graduate.
     *
     * @return array{action: 'promote'|'graduate'|'blocked', target: ?SchoolClass, source: 'rule', reason: ?string, label: string, message: ?string}
     */
    public static function runtimeDecisionFor(SchoolClass $class): array
    {
        $rule = ClassPromotionRule::query()
            ->where('source_class_id', $class->id)
            ->orderBy('id')
            ->first();

        if ($rule === null) {
            return static::blockedDecisionFor(
                reason: self::REASON_MISSING_RULE,
                label: 'Aturan belum dikonfigurasi',
                message: "Aturan kenaikan kelas untuk {$class->name} belum dikonfigurasi.",
            );
        }

        if (! $rule->is_active) {
            return static::blockedDecisionFor(
                reason: self::REASON_INACTIVE_RULE,
                label: 'Aturan tidak aktif',
                message: "Aturan kenaikan kelas untuk {$class->name} tidak aktif.",
            );
        }

        if ($rule->action === 'graduate') {
            return ['action' => 'graduate', 'target' => null, 'source' => 'rule', 'reason' => null, 'label' => 'Lulus', 'message' => null];
        }

        $target = $rule->targetClass;

        if ($target === null) {
            return static::blockedDecisionFor(
                reason: self::REASON_INVALID_TARGET,
                label: 'Kelas tujuan pada aturan tidak tersedia',
                message: "Kelas tujuan pada aturan {$class->name} tidak tersedia.",
            );
        }

        return ['action' => 'promote', 'target' => $target, 'source' => 'rule', 'reason' => null, 'label' => $target->name, 'message' => null];
    }

    /**
     * Alias guaranteeing a single runtime resolution path across the codebase.
     */
    public static function effectiveDecisionFor(SchoolClass $class): array
    {
        return static::runtimeDecisionFor($class);
    }

    /**
     * @param  string  $reason  One of the REASON_* constants.
     * @return array{action: 'blocked', target: null, source: 'rule', reason: string, label: string, message: string}
     */
    private static function blockedDecisionFor(string $reason, string $label, string $message): array
    {
        return [
            'action' => 'blocked',
            'target' => null,
            'source' => 'rule',
            'reason' => $reason,
            'label' => $label,
            'message' => $message,
        ];
    }

    /**
     * Determine if a class level is a structural graduation point.
     *
     * Graduation levels (TKB, 6, 9, 12) are a property of the school
     * structure, not of operator-configured rules, so this remains a fixed
     * structural check independent of class_promotion_rules. It backs the
     * import reuse assessment (StudentGraduationMarkerService).
     */
    public static function isGraduationLevel(int $level): bool
    {
        return in_array($level, [-1, 6, 9, 12], true);
    }
}
