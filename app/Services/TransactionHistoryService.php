<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\ProspectiveStudentPayment;
use App\Support\TransactionHistoryRow;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionHistoryService
{
    /** @return LengthAwarePaginator<int, TransactionHistoryRow> */
    public function getHistory(
        string $search = '',
        string $bankId = '',
        string $status = '',
        string $startDate = '',
        string $endDate = '',
        int $perPage = 10,
    ): LengthAwarePaginator {
        $studentQuery = $this->studentQuery($search, $bankId, $status, $startDate, $endDate);
        $prospectiveQuery = $this->prospectiveQuery($search, $bankId, $status, $startDate, $endDate);

        $paginator = $studentQuery
            ->unionAll($prospectiveQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = $paginator->items();
        $studentIds = collect($items)->where('source', 'student')->pluck('id')->all();
        $prospectiveIds = collect($items)->where('source', 'prospective')->pluck('id')->all();

        $students = $studentIds !== []
            ? Payment::query()
                ->with(['student.schoolClass', 'bank', 'user', 'details.bill'])
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
                    'details' => fn ($query) => $query->with(['paymentType', 'bill.paymentType']),
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
            ->when($status === 'cancelled', fn (QueryBuilder $query) => $query->where('payments.status', Payment::STATUS_CANCELLED))
            ->when(in_array($status, ['active', 'lunas'], true), fn (QueryBuilder $query) => $query->where('payments.status', '!=', Payment::STATUS_CANCELLED))
            ->when($startDate !== '', fn (QueryBuilder $query) => $query->whereDate('payments.created_at', '>=', $startDate))
            ->when($endDate !== '', fn (QueryBuilder $query) => $query->whereDate('payments.created_at', '<=', $endDate));
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
