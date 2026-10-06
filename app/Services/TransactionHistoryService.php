<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\ProspectiveStudentPayment;
use App\Support\TransactionHistoryRow;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionHistoryService
{
    /**
     * Kategori calon siswa pada filter riwayat.
     *
     * Kategori ini bukan status siswa, melainkan status akademik yang diturunkan:
     * sumber transaksi calon siswa (formulir) ditambah siswa hasil konversi yang
     * enrollment aktifnya hanya ada pada tahun ajaran yang akan datang.
     */
    private const CATEGORY_PROSPECTIVE = 'calon_siswa';

    /**
     * Status enrollment yang dianggap aktif pada tahun saat enrollment-nya dibuat.
     */
    private const ENROLLMENT_ACTIVE = 'active';

    /**
     * Opsi kategori siswa/calon siswa yang sah, memakai nilai enum yang sudah ada.
     *
     * @return array<string, string>
     */
    public static function studentCategoryOptions(): array
    {
        return [
            '' => 'Semua',
            StudentStatus::Active->value => 'Siswa Aktif',
            self::CATEGORY_PROSPECTIVE => 'Calon Siswa',
            StudentStatus::Graduated->value => 'Lulus',
            StudentStatus::Transferred->value => 'Pindah',
        ];
    }

    /**
     * Samakan kategori yang tidak dikenal ke "Semua" supaya query tidak pernah
     * menjadi tanpa filter secara tidak sengaja.
     */
    public static function normalizeStudentCategory(string $category): string
    {
        return array_key_exists($category, self::studentCategoryOptions())
            ? $category
            : '';
    }

    /** @return LengthAwarePaginator<int, TransactionHistoryRow> */
    public function getHistory(
        string $search = '',
        string $bankId = '',
        string $status = '',
        string $startDate = '',
        string $endDate = '',
        int $perPage = 10,
        string $studentCategory = '',
    ): LengthAwarePaginator {
        $category = self::normalizeStudentCategory($studentCategory);

        // Tahun ajaran aktif hanya boleh dibaca sekali: seluruh klasifikasi memakai
        // hasil yang sama supaya query tidak memanggil is_active berulang kali.
        $activeYear = AcademicYear::active();

        $studentQuery = fn (string $studentCategory = ''): QueryBuilder => $this->studentQuery(
            $search,
            $bankId,
            $status,
            $startDate,
            $endDate,
            $studentCategory,
            $activeYear,
        );
        $prospectiveQuery = $this->prospectiveQuery($search, $bankId, $status, $startDate, $endDate);

        // Kategori diterapkan sebelum union supaya tiap cabang hanya menyumbang barisnya
        // sendiri. Calon Siswa adalah gabungan sumber transaksi formulir dengan siswa
        // hasil konversi yang status akademiknya baru akan datang.
        $historyQuery = match (true) {
            $category === self::CATEGORY_PROSPECTIVE => $activeYear === null
                ? $prospectiveQuery
                : $studentQuery(self::CATEGORY_PROSPECTIVE)->unionAll($prospectiveQuery),
            $category !== '' => $studentQuery($category),
            default => $studentQuery()->unionAll($prospectiveQuery),
        };

        $paginator = $historyQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = $paginator->items();
        $studentIds = collect($items)->where('source', 'student')->pluck('id')->all();
        $prospectiveIds = collect($items)->where('source', 'prospective')->pluck('id')->all();

        // Relasi tagihan dipuat dua arah. Arah maju (payment -> details -> bill)
        // dipakai untuk menyusun label, sedangkan arah-balik (bill -> paymentDetails,
        // bill -> adjustments) dibutuhkan oleh accessor paid_amount/effective_amount
        // saat menghitung settlement. Tanpa pemuatan batch ini, setiap tagihan di
        // halaman memicu query sendiri di dalam loop pemetaan baris.
        $students = $studentIds !== []
            ? Payment::query()
                ->with([
                    'student.schoolClass',
                    'bank',
                    'user',
                    'details' => fn ($query) => $query->with([
                        'bill.adjustments',
                        'bill.paymentDetails.payment:id,status',
                    ]),
                ])
                ->whereIn('id', $studentIds)
                ->get()
                ->keyBy('id')
            : collect();
        $prospectives = $prospectiveIds !== []
            ? ProspectiveStudentPayment::query()
                ->with([
                    'prospectiveStudent.schoolClass',
                    'bank',
                    'creator',
                    'details' => fn ($query) => $query->with([
                        'paymentType',
                        'bill.paymentType',
                        'bill.paymentDetails.payment:id,status',
                    ]),
                ])
                ->whereIn('id', $prospectiveIds)
                ->get()
                ->keyBy('id')
            : collect();

        $rows = collect($items)->map(function (object $item) use ($students, $prospectives): TransactionHistoryRow {
            if ($item->source === 'prospective') {
                $payment = $prospectives->get($item->id);

                return $payment !== null
                    ? $this->prospectToRow($payment)
                    : $this->missingRow($item, 'prospective');
            }

            $payment = $students->get($item->id);

            return $payment !== null
                ? $this->toRow($payment)
                : $this->missingRow($item, 'student');
        });

        return $paginator->setCollection($rows);
    }

    private function studentQuery(
        string $search,
        string $bankId,
        string $status,
        string $startDate,
        string $endDate,
        string $studentCategory = '',
        ?AcademicYear $activeYear = null,
    ): QueryBuilder {
        return DB::table('payments')
            ->select('payments.id', 'payments.created_at')
            ->selectRaw("'student' as source")
            ->when($search !== '', function (QueryBuilder $query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function (QueryBuilder $query) use ($term): void {
                    $query->where('payments.receipt_number', 'like', $term)
                        ->orWhereExists(function ($exists) use ($term): void {
                            $exists->selectRaw('1')
                                ->from('students')
                                ->whereColumn('students.id', 'payments.student_id')
                                ->where(function (QueryBuilder $student) use ($term): void {
                                    $student->where('students.nama_lengkap', 'like', $term)
                                        ->orWhere('students.nama_panggilan', 'like', $term)
                                        ->orWhere('students.nis', 'like', $term);
                                });
                        });
                });
            })
            ->when($bankId !== '', fn (QueryBuilder $query) => $query->where('payments.bank_id', $bankId))
            ->when(
                $studentCategory !== '',
                fn (QueryBuilder $query) => $this->applyStudentCategory($query, $studentCategory, $activeYear),
            )
            ->when($status === 'cancelled', fn (QueryBuilder $query) => $query->where('payments.status', Payment::STATUS_CANCELLED))
            ->when(in_array($status, ['active', 'lunas'], true), fn (QueryBuilder $query) => $query->where('payments.status', '!=', Payment::STATUS_CANCELLED))
            ->when($startDate !== '', fn (QueryBuilder $query) => $query->whereDate('payments.created_at', '>=', $startDate))
            ->when($endDate !== '', fn (QueryBuilder $query) => $query->whereDate('payments.created_at', '<=', $endDate));
    }

    /**
     * Batasi cabang pembayaran siswa ke satu kategori riwayat.
     *
     * Kategori diturunkan di SQL memakai tabel students dan student_academic_enrollments
     * supaya tidak ada pemanggilan Student::academicStatus() per baris. Status stored
     * tetap menjadi penentu untuk status terminal (lulus/pindah).
     *
     * @param  string  $studentCategory  Nilai dari self::normalizeStudentCategory().
     */
    private function applyStudentCategory(
        QueryBuilder $query,
        string $studentCategory,
        ?AcademicYear $activeYear,
    ): void {
        // Status terminal memakai students.status apa adanya, enrollment masa depan
        // tidak boleh mengubahnya.
        if (in_array($studentCategory, [StudentStatus::Graduated->value, StudentStatus::Transferred->value], true)) {
            $this->constrainToStoredStudentStatus($query, $studentCategory);

            return;
        }

        $this->constrainToStoredStudentStatus($query, StudentStatus::Active->value);

        // Tanpa tahun ajaran aktif,Enrollment tidak bisa membedakan siswa aktif hari ini
        // dari calon siswa, sehingga status stored dipakai apa adanya.
        if ($activeYear === null) {
            return;
        }

        if ($studentCategory === self::CATEGORY_PROSPECTIVE) {
            $this->constrainToNoCurrentActiveEnrollment($query, $activeYear);
            $this->constrainToFutureActiveEnrollment($query, $activeYear);

            return;
        }

        // Siswa Aktif = punya enrollment aktif pada tahun ajaran aktif saat ini (a),
        // atau tidak punya enrollment tahun aktif maupun enrollment masa depan sebagai
        // fallback data lama (b). Karena (b) mensyaratkan ketiadaan (a), keduanya
        // ekuivalen dengan "a ATAU tidak ada enrollment masa depan".
        $query->where(function (QueryBuilder $query) use ($activeYear): void {
            $query->where(function (QueryBuilder $query) use ($activeYear): void {
                $this->constrainToCurrentActiveEnrollment($query, $activeYear);
            })->orWhere(function (QueryBuilder $query) use ($activeYear): void {
                $this->constrainToNoFutureActiveEnrollment($query, $activeYear);
            });
        });
    }

    private function constrainToStoredStudentStatus(QueryBuilder $query, string $studentStatus): void
    {
        $query->whereExists(
            fn (QueryBuilder $exists) => $exists->selectRaw('1')
                ->from('students')
                ->whereColumn('students.id', 'payments.student_id')
                ->where('students.status', $studentStatus)
        );
    }

    private function constrainToCurrentActiveEnrollment(QueryBuilder $query, AcademicYear $activeYear): void
    {
        $query->whereExists($this->activeEnrollmentSubquery($activeYear));
    }

    private function constrainToNoCurrentActiveEnrollment(QueryBuilder $query, AcademicYear $activeYear): void
    {
        $query->whereNotExists($this->activeEnrollmentSubquery($activeYear));
    }

    private function constrainToFutureActiveEnrollment(QueryBuilder $query, AcademicYear $activeYear): void
    {
        $query->whereExists($this->futureActiveEnrollmentSubquery($activeYear));
    }

    private function constrainToNoFutureActiveEnrollment(QueryBuilder $query, AcademicYear $activeYear): void
    {
        $query->whereNotExists($this->futureActiveEnrollmentSubquery($activeYear));
    }

    /**
     * Subquery enrollment aktif pada tahun ajaran aktif yang sudah di-resolve sekali.
     *
     * @return Closure(QueryBuilder): void
     */
    private function activeEnrollmentSubquery(AcademicYear $activeYear): Closure
    {
        return function (QueryBuilder $exists) use ($activeYear): void {
            $exists->selectRaw('1')
                ->from('student_academic_enrollments')
                ->whereColumn('student_academic_enrollments.student_id', 'payments.student_id')
                ->where('student_academic_enrollments.academic_year_id', $activeYear->id)
                ->where('student_academic_enrollments.status', self::ENROLLMENT_ACTIVE);
        };
    }

    /**
     * Subquery enrollment aktif pada tahun ajaran yang belum dimulai.
     *
     * @return Closure(QueryBuilder): void
     */
    private function futureActiveEnrollmentSubquery(AcademicYear $activeYear): Closure
    {
        return function (QueryBuilder $exists) use ($activeYear): void {
            $exists->selectRaw('1')
                ->from('student_academic_enrollments')
                ->join('academic_years', 'academic_years.id', '=', 'student_academic_enrollments.academic_year_id')
                ->whereColumn('student_academic_enrollments.student_id', 'payments.student_id')
                ->where('student_academic_enrollments.status', self::ENROLLMENT_ACTIVE)
                ->where('academic_years.start_date', '>', $activeYear->start_date->toDateString());
        };
    }

    private function prospectiveQuery(
        string $search,
        string $bankId,
        string $status,
        string $startDate,
        string $endDate,
    ): QueryBuilder {
        return DB::table('prospective_student_payments')
            ->select('prospective_student_payments.id', 'prospective_student_payments.created_at')
            ->selectRaw("'prospective' as source")
            ->when($search !== '', function (QueryBuilder $query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function (QueryBuilder $query) use ($term): void {
                    $query->where('prospective_student_payments.receipt_number', 'like', $term)
                        ->orWhereExists(function ($exists) use ($term): void {
                            $exists->selectRaw('1')
                                ->from('prospective_students')
                                ->whereColumn('prospective_students.id', 'prospective_student_payments.prospective_student_id')
                                ->where(function (QueryBuilder $student) use ($term): void {
                                    $student->where('prospective_students.nama_lengkap', 'like', $term)
                                        ->orWhere('prospective_students.nama_panggilan', 'like', $term)
                                        ->orWhere('prospective_students.registration_number', 'like', $term);
                                });
                        });
                });
            })
            ->when($bankId !== '', fn (QueryBuilder $query) => $query->where('prospective_student_payments.bank_id', $bankId))
            ->when($status === 'cancelled', fn (QueryBuilder $query) => $query->where('prospective_student_payments.status', ProspectiveStudentPayment::STATUS_CANCELLED))
            ->when(in_array($status, ['active', 'lunas'], true), fn (QueryBuilder $query) => $query->where('prospective_student_payments.status', '!=', ProspectiveStudentPayment::STATUS_CANCELLED))
            ->when($startDate !== '', fn (QueryBuilder $query) => $query->whereDate('prospective_student_payments.created_at', '>=', $startDate))
            ->when($endDate !== '', fn (QueryBuilder $query) => $query->whereDate('prospective_student_payments.created_at', '<=', $endDate));
    }

    private function toRow(Payment $payment): TransactionHistoryRow
    {
        $isActive = $payment->isActive();
        $isManual = $payment->isManualPayment();

        return new TransactionHistoryRow(
            id: $payment->id,
            receiptNumber: $payment->receipt_number,
            name: $payment->student?->nama_lengkap ?? '—',
            secondaryInfo: 'NIS '.($payment->student?->nis ?? '—').' • Kelas '.($payment->student?->schoolClass?->name ?? '—'),
            detailDisplay: $payment->detail_display,
            bankName: $payment->bank?->paymentLabel() ?? '—',
            bankAccountNumber: $payment->bank?->displayAccountNumber(),
            totalAmount: (float) $payment->total_amount,
            paymentDate: $payment->payment_date,
            createdAt: $payment->created_at,
            statusLabel: $payment->status_label,
            creatorName: $payment->user?->name ?? 'Administrator',
            badgeLabel: $payment->kind_label,
            badgeClasses: $isManual ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800',
            detailUrl: route('pembayaran.show', $payment->id),
            editUrl: $isActive
                ? ($isManual ? route('pembayaran.manual.edit', $payment) : route('pembayaran.edit', $payment))
                : null,
            isActive: $isActive,
            source: 'student',
        );
    }

    private function prospectToRow(ProspectiveStudentPayment $payment): TransactionHistoryRow
    {
        $isActive = $payment->isActive();

        return new TransactionHistoryRow(
            id: 'prospect-'.$payment->id,
            receiptNumber: $payment->receipt_number,
            name: $payment->prospectiveStudent?->nama_lengkap ?? '—',
            secondaryInfo: 'REG '.($payment->prospectiveStudent?->registration_number ?? '—').' • Kelas '.($payment->prospectiveStudent?->schoolClass?->name ?? '—'),
            detailDisplay: $this->prospectDetailDisplay($payment),
            bankName: $payment->bank?->paymentLabel() ?? '—',
            bankAccountNumber: $payment->bank?->displayAccountNumber(),
            totalAmount: (float) $payment->total_amount,
            paymentDate: $payment->payment_date,
            createdAt: $payment->created_at,
            statusLabel: $payment->status_label,
            creatorName: $payment->created_by !== null
                ? ($payment->creator?->name ?? 'Administrator')
                : 'Administrator',
            badgeLabel: 'Pendaftaran',
            badgeClasses: 'bg-emerald-100 text-emerald-800',
            detailUrl: route('pembayaran.prospective.show', $payment),
            editUrl: $isActive ? route('pembayaran.prospective.edit', $payment) : null,
            isActive: $isActive,
            source: 'prospective',
        );
    }

    private function missingRow(object $item, string $source): TransactionHistoryRow
    {
        return new TransactionHistoryRow(
            id: $source === 'prospective' ? 'prospect-'.$item->id : (int) $item->id,
            receiptNumber: '—',
            name: 'Data tidak ditemukan',
            secondaryInfo: '',
            detailDisplay: '',
            bankName: '—',
            bankAccountNumber: null,
            totalAmount: 0.0,
            paymentDate: null,
            createdAt: null,
            statusLabel: '—',
            creatorName: '',
            badgeLabel: '',
            badgeClasses: 'bg-slate-100 text-slate-700',
            detailUrl: '#',
            editUrl: null,
            isActive: false,
            source: $source,
        );
    }

    private function prospectDetailDisplay(ProspectiveStudentPayment $payment): string
    {
        $details = $payment->relationLoaded('details')
            ? $payment->details
            : $payment->details()->with(['paymentType', 'bill.paymentType'])->get();

        /** @var Collection<int, string> $labels */
        $labels = $details
            ->map(function ($detail): string {
                $paymentType = $detail->getRelation('paymentType');

                if ($paymentType instanceof PaymentType) {
                    return trim((string) $paymentType->name);
                }

                $bill = $detail->getRelation('bill');
                $billType = $bill !== null ? $bill->getRelation('paymentType') : null;

                return trim((string) ($billType instanceof PaymentType ? $billType->name : $detail->description));
            })
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return '-';
        }

        if ($labels->count() <= 2) {
            return $labels->implode(' + ');
        }

        return $labels->first().' + '.($labels->count() - 1).' lainnya';
    }
}
