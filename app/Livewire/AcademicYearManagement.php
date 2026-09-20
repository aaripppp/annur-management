<?php

namespace App\Livewire;

use App\Exceptions\BlockedPromotionException;
use App\Models\AcademicYear;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AcademicYearManagement extends Component
{
    public ?int $activeYearId = null;

    public string $activeYearLabel = '';

    public ?int $newYearId = null;

    public string $newYearLabel = '';

    public string $newYearStartDate = '';

    public bool $isCreateFormOpen = false;

    public bool $isPreviewOpen = false;

    public bool $isConfirmOpen = false;

    public array $previewGrouped = [];

    public int $previewTotalPromoted = 0;

    public int $previewTotalGraduated = 0;

    public int $previewTotalBlocked = 0;

    public array $previewBlockedTerms = [];

    public bool $isAlreadyProcessed = false;

    public string $processingMessage = '';

    public bool $isBlockedOpen = false;

    public string $blockedMessage = '';

    public array $blockedTerms = [];

    // Monthly bill generation
    public ?int $monthlyTargetYearId = null;

    public bool $isMonthlyPreviewOpen = false;

    public bool $isMonthlyConfirmOpen = false;

    public int $monthlyPreviewEligibleStudents = 0;

    public int $monthlyPreviewWillCreate = 0;

    public int $monthlyPreviewAlreadyExisting = 0;

    public array $monthlyPreviewTariffs = [];

    public string $monthlyPreviewAcademicYear = '';

    public bool $isMonthlyAlreadyGenerated = false;

    public string $monthlyProcessingMessage = '';

    public function mount(): void
    {
        $this->loadActiveYear();
        $this->loadNewYear();
    }

    public function loadActiveYear(): void
    {
        $active = AcademicYear::active();

        if ($active) {
            $this->activeYearId = $active->id;
            $this->activeYearLabel = $active->year;
        } else {
            $this->activeYearId = null;
            $this->activeYearLabel = '-';
        }
    }

    public function loadNewYear(): void
    {
        $newYear = AcademicYear::next();

        if ($newYear) {
            $this->newYearId = $newYear->id;
            $this->newYearLabel = $newYear->year;
            $this->newYearStartDate = $newYear->start_date->format('Y-m-d');
        } else {
            $this->newYearId = null;
            $this->newYearLabel = '';
            $this->newYearStartDate = '';
        }
    }

    public function openCreateForm(): void
    {
        $this->isCreateFormOpen = true;
        $this->resetValidation();
    }

    public function closeCreateForm(): void
    {
        $this->isCreateFormOpen = false;
        $this->resetValidation();
    }

    public function saveNewYear(): void
    {
        $active = AcademicYear::active();

        $this->validate([
            'newYearLabel' => [
                'required',
                'string',
                'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('academic_years', 'year'),
            ],
        ]);

        [$startYear] = explode('/', $this->newYearLabel);

        AcademicYear::create([
            'year' => $this->newYearLabel,
            'is_active' => false,
            'start_date' => $startYear.'-07-01',
            'end_date' => ($startYear + 1).'-06-30',
        ]);

        $this->isCreateFormOpen = false;
        $this->loadNewYear();
        session()->flash('success', "Tahun ajaran {$this->newYearLabel} berhasil dibuat.");
    }

    public function openPreview(): void
    {
        if (! $this->activeYearId || ! $this->newYearId) {
            return;
        }

        $active = AcademicYear::findOrFail($this->activeYearId);
        $newYear = AcademicYear::findOrFail($this->newYearId);

        $service = app(ClassPromotionService::class);

        if ($service->isAlreadyProcessed($newYear)) {
            $this->isAlreadyProcessed = true;
            $this->processingMessage = "Proses kenaikan kelas untuk tahun ajaran {$newYear->year} sudah pernah dijalankan.";

            return;
        }

        $this->isAlreadyProcessed = false;
        $preview = $service->getPreviewData($active, $newYear);
        $this->previewGrouped = $preview['grouped']->toArray();
        $this->previewTotalPromoted = $preview['summary']['total_promoted'];
        $this->previewTotalGraduated = $preview['summary']['total_graduated'];
        $this->previewTotalBlocked = $preview['summary']['total_blocked'];
        $this->previewBlockedTerms = collect($service->blockedMappings($preview['grouped']))
            ->map(fn (array $mapping): string => sprintf(
                '%s → %s',
                $mapping['source_class']?->name ?? '—',
                $mapping['reason_label'],
            ))
            ->values()
            ->all();
        $this->isPreviewOpen = true;
    }

    public function closePreview(): void
    {
        $this->isPreviewOpen = false;
        $this->previewGrouped = [];
        $this->previewTotalPromoted = 0;
        $this->previewTotalGraduated = 0;
        $this->previewTotalBlocked = 0;
        $this->previewBlockedTerms = [];
    }

    public function openConfirm(): void
    {
        $this->isPreviewOpen = false;
        $this->isConfirmOpen = true;
    }

    public function closeConfirm(): void
    {
        $this->isConfirmOpen = false;
    }

    public function executePromotion(): void
    {
        if (! $this->activeYearId || ! $this->newYearId) {
            return;
        }

        $active = AcademicYear::findOrFail($this->activeYearId);
        $newYear = AcademicYear::findOrFail($this->newYearId);

        $service = app(ClassPromotionService::class);

        try {
            $result = $service->processPromotion($active, $newYear);
        } catch (BlockedPromotionException $exception) {
            $this->isConfirmOpen = false;
            $this->isBlockedOpen = true;
            $this->blockedMessage = 'Promosi belum dapat diproses karena terdapat kelas sumber tanpa aturan aktif yang valid.';
            $this->blockedTerms = collect($exception->blockedMappings())
                ->map(fn (array $mapping): string => sprintf(
                    '%s → %s',
                    $mapping['source_class']?->name ?? '—',
                    $mapping['reason_label'],
                ))
                ->values()
                ->all();

            return;
        }

        $this->isConfirmOpen = false;
        $this->loadActiveYear();
        $this->loadNewYear();
        $this->previewGrouped = [];
        $this->previewTotalPromoted = 0;
        $this->previewTotalGraduated = 0;
        $this->previewTotalBlocked = 0;
        $this->previewBlockedTerms = [];

        $msg = "Proses kenaikan kelas selesai. {$result['promoted']} siswa naik kelas, {$result['graduated']} siswa lulus.";
        session()->flash('success', $msg);
    }

    public function closeBlocked(): void
    {
        $this->isBlockedOpen = false;
        $this->blockedMessage = '';
        $this->blockedTerms = [];
    }

    // ─── Monthly Bill Generation ───────────────────────────────────────

    public function openMonthlyPreview(?int $yearId = null): void
    {
        $targetYearId = $yearId ?? $this->newYearId ?? $this->activeYearId;

        if (! $targetYearId) {
            return;
        }

        $academicYear = AcademicYear::findOrFail($targetYearId);
        $service = app(BillGenerationService::class);
        $preview = $service->getMonthlyGenerationPreview($academicYear);

        $this->monthlyTargetYearId = $targetYearId;
        $this->monthlyPreviewAcademicYear = $preview['academic_year'];
        $this->monthlyPreviewEligibleStudents = $preview['eligible_students'];
        $this->monthlyPreviewWillCreate = $preview['will_create'];
        $this->monthlyPreviewAlreadyExisting = $preview['already_existing'];
        $this->monthlyPreviewTariffs = $preview['tariffs'];
        $this->isMonthlyAlreadyGenerated = false;

        if ($preview['eligible_students'] === 0) {
            $this->isMonthlyAlreadyGenerated = true;
            $this->monthlyProcessingMessage = "Tidak ada siswa aktif yang terdaftar di tahun ajaran {$preview['academic_year']}.";
        } elseif ($preview['will_create'] === 0 && $preview['already_existing'] > 0) {
            $this->isMonthlyAlreadyGenerated = true;
            $this->monthlyProcessingMessage = "Semua tagihan bulanan untuk tahun ajaran {$preview['academic_year']} sudah digenerate.";
        }

        $this->isMonthlyPreviewOpen = true;
    }

    public function closeMonthlyPreview(): void
    {
        $this->isMonthlyPreviewOpen = false;
        $this->monthlyTargetYearId = null;
        $this->monthlyPreviewEligibleStudents = 0;
        $this->monthlyPreviewWillCreate = 0;
        $this->monthlyPreviewAlreadyExisting = 0;
        $this->monthlyPreviewTariffs = [];
        $this->monthlyPreviewAcademicYear = '';
        $this->isMonthlyAlreadyGenerated = false;
        $this->monthlyProcessingMessage = '';
    }

    public function openMonthlyConfirm(): void
    {
        $this->isMonthlyPreviewOpen = false;
        $this->isMonthlyConfirmOpen = true;
    }

    public function closeMonthlyConfirm(): void
    {
        $this->isMonthlyConfirmOpen = false;
    }

    public function executeMonthlyGeneration(): void
    {
        if (! $this->monthlyTargetYearId) {
            return;
        }

        $academicYear = AcademicYear::findOrFail($this->monthlyTargetYearId);
        $service = app(BillGenerationService::class);
        $result = $service->generateMonthlyForAcademicYear($academicYear);

        $this->isMonthlyConfirmOpen = false;

        $this->monthlyPreviewEligibleStudents = 0;
        $this->monthlyPreviewWillCreate = 0;
        $this->monthlyPreviewAlreadyExisting = 0;
        $this->monthlyPreviewTariffs = [];
        $this->monthlyPreviewAcademicYear = '';

        $msg = "Tagihan bulanan untuk tahun ajaran {$academicYear->year} berhasil dibuat. {$result['created']} tagihan baru, {$result['skipped']} tagihan sudah ada (dilewati).";
        session()->flash('success', $msg);
    }

    public function render()
    {
        $academicYears = AcademicYear::orderBy('start_date')->get();

        return view('livewire.academic-year.index', [
            'academicYears' => $academicYears,
        ]);
    }
}
