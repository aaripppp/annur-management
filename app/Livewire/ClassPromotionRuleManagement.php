<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\ClassPromotionRule;
use App\Models\ClassPromotionRuleLog;
use App\Models\SchoolClass;
use App\Services\ClassPromotionRuleAuditService;
use App\Support\ClassPromotionMapping;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ClassPromotionRuleManagement extends Component
{
    public string $filterJenjang = SchoolLevel::SD->value;

    public bool $isModalOpen = false;

    public ?int $ruleId = null;

    public ?int $sourceClassId = null;

    public string $sourceClassName = '';

    public string $action = 'promote';

    public ?int $targetClassId = null;

    public bool $isActive = true;

    public bool $isDeleteModalOpen = false;

    public ?int $deletingRuleId = null;

    public string $deleteSourceClassName = '';

    public bool $showHistoryModal = false;

    public ?int $historyToDeleteId = null;

    public bool $showDeleteHistoryModal = false;

    public bool $showDeleteAllHistoryModal = false;

    public function setJenjang(string $jenjang): void
    {
        if (SchoolLevel::tryFrom($jenjang) !== null) {
            $this->filterJenjang = $jenjang;
        }
    }

    public function updatedAction(): void
    {
        if ($this->action === 'graduate') {
            $this->targetClassId = null;
        }
    }

    public function openCreate(int $sourceClassId): void
    {
        $class = SchoolClass::findOrFail($sourceClassId);
        $decision = ClassPromotionMapping::effectiveDecisionFor($class);

        $this->ruleId = null;
        $this->sourceClassId = $class->id;
        $this->sourceClassName = $class->name.' • '.$class->level_name;
        $this->action = $decision['action'] === 'graduate' ? 'graduate' : 'promote';
        $this->targetClassId = $decision['target']?->id;
        $this->isActive = true;
        $this->isModalOpen = true;
        $this->resetValidation();
    }

    public function edit(int $sourceClassId): void
    {
        $rule = ClassPromotionRule::query()
            ->where('source_class_id', $sourceClassId)
            ->firstOrFail();

        $class = SchoolClass::findOrFail($sourceClassId);

        $this->ruleId = $rule->id;
        $this->sourceClassId = $rule->source_class_id;
        $this->sourceClassName = $class->name.' • '.$class->level_name;
        $this->action = $rule->action;
        $this->targetClassId = $rule->target_class_id;
        $this->isActive = $rule->is_active;
        $this->isModalOpen = true;
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['ruleId', 'sourceClassId', 'sourceClassName', 'action', 'targetClassId', 'isActive']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'sourceClassId' => [
                'required',
                'integer',
                'exists:school_classes,id',
                Rule::unique('class_promotion_rules', 'source_class_id')->ignore($this->ruleId),
            ],
            'action' => ['required', Rule::in(['promote', 'graduate'])],
            'targetClassId' => ['nullable', 'integer', 'exists:school_classes,id'],
            'isActive' => ['boolean'],
        ]);

        if ($this->action === 'promote') {
            $this->validate([
                'targetClassId' => ['required', 'integer', 'different:sourceClassId', 'exists:school_classes,id'],
            ]);

            if (! $this->isForwardTarget()) {
                $this->addError('targetClassId', 'Kelas tujuan berada pada jenjang di bawah kelas asal (pemetaan mundur) dan dilarang.');

                return;
            }

            if ($this->createsCycle()) {
                $this->addError('targetClassId', 'Pemetaan ini membentuk siklus dua arah dengan aturan kelas tujuan yang sudah ada dan dilarang.');

                return;
            }
        } elseif ($this->targetClassId !== null) {
            $this->addError('targetClassId', 'Kelas tujuan harus kosong saat aksi yang dipilih adalah Lulus.');

            return;
        } else {
            $this->targetClassId = null;
        }

        $data = [
            'source_class_id' => $this->sourceClassId,
            'action' => $this->action,
            'target_class_id' => $this->action === 'promote' ? $this->targetClassId : null,
            'is_active' => $this->isActive,
        ];

        $audit = app(ClassPromotionRuleAuditService::class);

        if ($this->ruleId) {
            $rule = ClassPromotionRule::findOrFail($this->ruleId);
            $oldState = $this->stateOf($rule);
            $wasActive = $rule->is_active;
            $rule->update($data);
            $audit->editedManual($rule, $oldState, auth()->id());

            if ($wasActive && ! $rule->is_active) {
                $audit->deactivated($rule, auth()->id());
            } elseif (! $wasActive && $rule->is_active) {
                $audit->activated($rule, auth()->id());
            }

            $message = 'Aturan kenaikan kelas berhasil diupdated.';
        } else {
            $rule = ClassPromotionRule::create($data);
            $audit->createdManual($rule, auth()->id());
            $message = 'Aturan kenaikan kelas baru berhasil dibuat.';
        }

        $this->closeModal();
        session()->flash('success', $message);
    }

    public function confirmDelete(int $ruleId): void
    {
        $rule = ClassPromotionRule::findOrFail($ruleId);
        $this->deletingRuleId = $rule->id;
        $this->deleteSourceClassName = $rule->sourceClass?->name ?? '—';
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingRuleId = null;
        $this->deleteSourceClassName = '';
        $this->isDeleteModalOpen = false;
    }

    public function deleteRule(): void
    {
        if (! $this->deletingRuleId) {
            return;
        }

        $rule = ClassPromotionRule::with('sourceClass')->findOrFail($this->deletingRuleId);

        if ($rule->sourceClass === null) {
            $this->cancelDelete();

            return;
        }

        $oldState = $this->stateOf($rule);
        $className = $rule->sourceClass->name;

        app(ClassPromotionRuleAuditService::class)->deleted($rule, $oldState, auth()->id());

        $rule->delete();

        $this->cancelDelete();
        session()->flash('success', "Aturan kelas {$className} berhasil dihapus.");
    }

    public function openHistory(): void
    {
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->cancelDeleteHistory();
        $this->cancelDeleteAllHistory();
    }

    public function confirmDeleteHistory(int $historyId): void
    {
        $this->historyToDeleteId = $historyId;
        $this->showDeleteHistoryModal = true;
    }

    public function cancelDeleteHistory(): void
    {
        $this->historyToDeleteId = null;
        $this->showDeleteHistoryModal = false;
    }

    public function deleteHistory(): void
    {
        if ($this->historyToDeleteId === null) {
            return;
        }

        ClassPromotionRuleLog::whereKey($this->historyToDeleteId)->delete();

        $this->cancelDeleteHistory();

        session()->flash('success', 'Riwayat perubahan berhasil dihapus.');
    }

    public function openDeleteAllHistory(): void
    {
        $this->showDeleteAllHistoryModal = true;
    }

    public function cancelDeleteAllHistory(): void
    {
        $this->showDeleteAllHistoryModal = false;
    }

    public function deleteAllHistory(): void
    {
        ClassPromotionRuleLog::query()->delete();

        $this->showDeleteAllHistoryModal = false;

        session()->flash('success', 'Semua riwayat perubahan berhasil dihapus.');
    }

    private function isForwardTarget(): bool
    {
        if ($this->action !== 'promote' || $this->sourceClassId === null || $this->targetClassId === null) {
            return true;
        }

        $source = SchoolClass::find($this->sourceClassId);
        $target = SchoolClass::find($this->targetClassId);

        if ($source === null || $target === null) {
            return true;
        }

        return (int) $target->level >= (int) $source->level;
    }

    private function createsCycle(): bool
    {
        if ($this->action !== 'promote' || $this->sourceClassId === null || $this->targetClassId === null) {
            return false;
        }

        return ClassPromotionRule::query()
            ->where('source_class_id', $this->targetClassId)
            ->where('target_class_id', $this->sourceClassId)
            ->where('action', 'promote')
            ->where('is_active', true)
            ->when($this->ruleId !== null, fn ($query) => $query->whereKeyNot($this->ruleId))
            ->exists();
    }

    private function stateOf(ClassPromotionRule $rule): array
    {
        return [
            'action' => $rule->action,
            'target_class_id' => $rule->target_class_id,
            'is_active' => $rule->is_active,
        ];
    }

    public function messages(): array
    {
        return [
            'sourceClassId.required' => 'Kelas asal wajib dipilih.',
            'sourceClassId.unique' => 'Aturan untuk kelas asal ini sudah dibuat.',
            'action.required' => 'Aksi wajib dipilih.',
            'action.in' => 'Aksi yang dipilih tidak valid.',
            'targetClassId.required' => 'Kelas tujuan wajib dipilih saat aksi adalah Naik Kelas.',
            'targetClassId.different' => 'Kelas tujuan tidak boleh sama dengan kelas asal.',
            'targetClassId.exists' => 'Kelas tujuan tidak ditemukan.',
        ];
    }

    public function render()
    {
        $jenjang = SchoolLevel::tryFrom($this->filterJenjang);

        $rules = ClassPromotionRule::query()->get()->keyBy('source_class_id');
        $allClasses = SchoolClass::query()->orderBy('level')->orderBy('name')->get();

        $visibleClasses = $jenjang === null
            ? $allClasses
            : $allClasses->filter(fn (SchoolClass $class) => in_array((int) $class->level, $jenjang->classLevels(), true));

        $rows = $visibleClasses->map(function (SchoolClass $class) use ($rules) {
            $rule = $rules->get($class->id);

            return [
                'class' => $class,
                'decision' => ClassPromotionMapping::effectiveDecisionFor($class),
                'rule' => $rule,
                'rule_status' => $rule === null
                    ? 'missing'
                    : ($rule->is_active ? 'active' : 'inactive'),
            ];
        })->values();

        $targetClassOptions = SchoolClass::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return view('livewire.class-promotion-rule.index', [
            'rows' => $rows,
            'targetClassOptions' => $targetClassOptions,
            'jenjangs' => SchoolLevel::cases(),
            'totalRules' => $rules->count(),
            'coverage' => [
                'total_classes' => $allClasses->count(),
                'active_rule_count' => $rules->filter(fn (ClassPromotionRule $rule) => $rule->is_active)->count(),
                'inactive_rule_count' => $rules->filter(fn (ClassPromotionRule $rule) => ! $rule->is_active)->count(),
                'missing_rule_count' => $allClasses->count() - $rules->count(),
            ],
            'history' => $this->showHistoryModal
                ? ClassPromotionRuleLog::with(['sourceClass', 'changedBy'])->latest('id')->limit(50)->get()
                    ->map(function (ClassPromotionRuleLog $log) use ($allClasses) {
                        $names = $allClasses->pluck('name', 'id');

                        return [
                            'id' => $log->id,
                            'created_at' => $log->created_at,
                            'source_class_name' => $log->sourceClass?->name ?? '—',
                            'action_type' => $log->action_type,
                            'old_label' => $this->ruleStateLabel($log->old_action, $log->old_target_class_id, $names),
                            'new_label' => $this->ruleStateLabel($log->new_action, $log->new_target_class_id, $names),
                            'changed_by_name' => $log->changedBy?->name ?? 'Sistem',
                            'metadata' => $log->metadata ?? [],
                        ];
                    })
                    ->values()
                : collect(),
        ]);
    }

    private function ruleStateLabel(?string $action, ?int $targetClassId, Collection $classNames): string
    {
        if ($action === null) {
            return '—';
        }

        if ($action === 'graduate') {
            return 'Lulus';
        }

        return 'Naik ke '.($classNames[$targetClassId] ?? '—');
    }
}
