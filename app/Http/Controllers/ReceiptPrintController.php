<?php

namespace App\Http\Controllers;

use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\User;
use App\Support\PaymentReceiptDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ReceiptPrintController extends Controller
{
    private const PDF_WIDTH_PT = 612.28;

    private const PDF_HEIGHT_PT = 935.43;

    public function student(Payment $payment): Response
    {
        return $this->respond(
            $this->studentReceipt($payment),
            $payment->receipt_number.'.pdf',
            download: false
        );
    }

    public function daycare(DaycarePayment $payment): Response
    {
        return $this->respond(
            $this->daycareReceipt($payment),
            $payment->receipt_number.'.pdf',
            download: false
        );
    }

    public function studentPdf(Payment $payment): Response
    {
        return $this->respond(
            $this->studentReceipt($payment),
            $payment->receipt_number.'.pdf',
            download: true
        );
    }

    public function daycarePdf(DaycarePayment $payment): Response
    {
        return $this->respond(
            $this->daycareReceipt($payment),
            $payment->receipt_number.'.pdf',
            download: true
        );
    }

    public function prospective(ProspectiveStudentPayment $payment): Response
    {
        return $this->respond(
            $this->prospectiveReceipt($payment),
            $payment->receipt_number.'.pdf',
            download: false
        );
    }

    public function prospectivePdf(ProspectiveStudentPayment $payment): Response
    {
        return $this->respond(
            $this->prospectiveReceipt($payment),
            $payment->receipt_number.'.pdf',
            download: true
        );
    }

    /**
     * @return array{
     *     category: string,
     *     receiptNumber: string,
     *     badge: string,
     *     paymentDate: string,
     *     identityLabel: string,
     *     identityName: string,
     *     identityContext: string,
     *     bankName: string,
     *     bankAccountNumber: string|null,
     *     bankAccountName: string|null,
     *     creatorName: string|null,
     *     creatorPosition: string,
     *     authorizationDate: string|null,
     *     notes: string|null,
     *     details: array<int, array{name: string, description: string|null, amount: float}>,
     *     total: float
     * }
     */
    private function studentReceipt(Payment $payment): array
    {
        $payment->load(['student.schoolClass', 'bank', 'user', 'details.paymentType']);

        $details = PaymentReceiptDisplay::rows($payment)->all();

        if ($details === []) {
            $details[] = [
                'name' => $payment->description ?: ($payment->isManualPayment() ? 'Pembayaran Manual' : 'Pembayaran SPP'),
                'description' => null,
                'amount' => (float) $payment->total_amount,
            ];
        }

        $paymentDate = Carbon::parse($payment->payment_date);
        $paymentDate->locale('id');
        $authorizationDate = $payment->created_at
            ->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('d F Y');
        $bank = $payment->bank;
        $student = $payment->student;
        $creator = $payment->user;
        $identityContext = $student->academicStatus() === 'calon_siswa'
            ? (filled($student->nis) ? 'NIS '.$student->nis.' • Calon Siswa' : 'Calon Siswa')
            : 'NIS '.$student->nis.' • Kelas '.($student->schoolClass->name ?? '—');

        return [
            'category' => $payment->isManualPayment() ? 'Siswa • Manual' : 'Siswa',
            'receiptNumber' => $payment->receipt_number,
            'badge' => $payment->status_label,
            'paymentDate' => $paymentDate->translatedFormat('d F Y'),
            'identityLabel' => 'Informasi Siswa',
            'identityName' => $student->nama_lengkap,
            'identityContext' => $identityContext,
            'bankName' => $bank->paymentLabel(),
            'bankAccountNumber' => $bank->isBank() ? $bank->account_number : null,
            'bankAccountName' => $bank->isBank() ? $bank->account_name : null,
            'creatorName' => $creator?->name ?? 'Administrator',
            'creatorPosition' => $this->creatorPosition($creator),
            'authorizationDate' => $authorizationDate,
            'notes' => $payment->isManualPayment() ? $payment->description : null,
            'details' => $details,
            'total' => (float) $payment->total_amount,
        ];
    }

    /**
     * @return array{
     *     category: string,
     *     receiptNumber: string,
     *     badge: string,
     *     paymentDate: string,
     *     identityLabel: string,
     *     identityName: string,
     *     identityContext: string,
     *     bankName: string,
     *     bankAccountNumber: string|null,
     *     bankAccountName: string|null,
     *     creatorName: string|null,
     *     creatorPosition: string,
     *     authorizationDate: string|null,
     *     notes: string|null,
     *     details: array<int, array{name: string, description: string|null, amount: float}>,
     *     total: float
     * }
     */
    private function daycareReceipt(DaycarePayment $payment): array
    {
        $payment->load(['child', 'bank', 'details', 'creator']);

        $details = $payment->details->map(fn (DaycarePaymentDetail $detail): array => [
            'name' => $detail->description,
            'description' => null,
            'amount' => (float) $detail->amount,
        ])->all();

        $paymentDate = Carbon::parse($payment->payment_date);
        $paymentDate->locale('id');
        $authorizationDate = $payment->created_at
            ->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('d F Y');
        $bank = $payment->bank;
        $creator = $payment->creator;

        return [
            'category' => 'Daycare',
            'receiptNumber' => $payment->receipt_number,
            'badge' => 'Pembayaran Daycare',
            'paymentDate' => $paymentDate->translatedFormat('d F Y'),
            'identityLabel' => 'Informasi Anak',
            'identityName' => $payment->child->nama_lengkap,
            'identityContext' => 'Daycare • Kelas '.$payment->child->kelas,
            'bankName' => $bank->paymentLabel(),
            'bankAccountNumber' => $bank->isBank() ? $bank->account_number : null,
            'bankAccountName' => $bank->isBank() ? $bank->account_name : null,
            'creatorName' => $creator?->name ?? 'Administrator',
            'creatorPosition' => $this->creatorPosition($creator),
            'authorizationDate' => $authorizationDate,
            'notes' => null,
            'details' => $details,
            'total' => (float) $payment->total_amount,
        ];
    }

    /**
     * @return array{
     *     category: string,
     *     receiptNumber: string,
     *     badge: string,
     *     paymentDate: string,
     *     identityLabel: string,
     *     identityName: string,
     *     identityContext: string,
     *     bankName: string,
     *     bankAccountNumber: string|null,
     *     bankAccountName: string|null,
     *     creatorName: string|null,
     *     creatorPosition: string,
     *     authorizationDate: string|null,
     *     notes: string|null,
     *     details: array<int, array{name: string, description: string|null, amount: float}>,
     *     total: float
     * }
     */
    private function prospectiveReceipt(ProspectiveStudentPayment $payment): array
    {
        $payment->load([
            'prospectiveStudent.academicYear',
            'prospectiveStudent.schoolClass',
            'bank',
            'creator',
            'details.paymentType',
        ]);

        $details = $payment->details->map(fn (ProspectiveStudentPaymentDetail $detail): array => [
            'name' => $detail->paymentType?->name ?? 'Item Pembayaran',
            'description' => $detail->description,
            'amount' => (float) $detail->amount,
        ])->all();

        if ($details === []) {
            $details[] = [
                'name' => $payment->description ?: 'Formulir Pendaftaran',
                'description' => null,
                'amount' => (float) $payment->total_amount,
            ];
        }

        $paymentDate = Carbon::parse($payment->payment_date);
        $paymentDate->locale('id');
        $authorizationDate = $payment->created_at
            ?->copy()
            ?->timezone('Asia/Jakarta')
            ?->locale('id')
            ?->translatedFormat('d F Y');
        $bank = $payment->bank;
        $prospectiveStudent = $payment->prospectiveStudent;
        $creator = $payment->creator;

        return [
            'category' => 'Calon Siswa',
            'receiptNumber' => $payment->receipt_number,
            'badge' => $payment->status_label,
            'paymentDate' => $paymentDate->translatedFormat('d F Y'),
            'identityLabel' => 'Informasi Calon Siswa',
            'identityName' => $prospectiveStudent->nama_lengkap ?? '—',
            'identityContext' => filled($prospectiveStudent->registration_number)
                ? $prospectiveStudent->registration_number.' • Calon Siswa'
                : 'Calon Siswa',
            'bankName' => $bank->paymentLabel(),
            'bankAccountNumber' => $bank->isBank() ? $bank->account_number : null,
            'bankAccountName' => $bank->isBank() ? $bank->account_name : null,
            'creatorName' => $creator?->name ?? 'Administrator',
            'creatorPosition' => $this->creatorPosition($creator),
            'authorizationDate' => $authorizationDate,
            'notes' => $payment->description,
            'details' => $details,
            'total' => (float) $payment->total_amount,
        ];
    }

    private function creatorPosition(?User $creator): string
    {
        if ($creator === null) {
            return 'Administrator';
        }

        return filled($creator->position) ? $creator->position : $creator->roleLabel();
    }

    /**
     * @param  array{
     *     category: string,
     *     receiptNumber: string,
     *     badge: string,
     *     paymentDate: string,
     *     identityLabel: string,
     *     identityName: string,
     *     identityContext: string,
     *     bankName: string,
     *     bankAccountNumber: string|null,
     *     bankAccountName: string|null,
     *     creatorName: string|null,
     *     creatorPosition: string,
     *     authorizationDate: string|null,
     *     notes: string|null,
     *     details: array<int, array{name: string, description: string|null, amount: float}>,
     *     total: float
     * } $receipt
     */
    private function respond(array $receipt, string $filename, bool $download): Response
    {
        $pdf = Pdf::loadView('receipts.pdf', compact('receipt'))
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false])
            ->setPaper([0, 0, self::PDF_WIDTH_PT, self::PDF_HEIGHT_PT]);

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
