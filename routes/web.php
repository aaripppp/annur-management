<?php

use App\Http\Controllers\DaycareDailyReportExportController;
use App\Http\Controllers\DaycareDailyReportHistoryPdfController;
use App\Http\Controllers\DaycareDailyReportPdfController;
use App\Http\Controllers\DaycareMonthlyReportExportController;
use App\Http\Controllers\DaycareMonthlyReportPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptPrintController;
use App\Http\Controllers\SchoolBankRecapExportController;
use App\Http\Controllers\SchoolBankRecapPdfController;
use App\Http\Controllers\SchoolDailyReportExportController;
use App\Http\Controllers\SchoolDailyReportHistoryPdfController;
use App\Http\Controllers\SchoolDailyReportPdfController;
use App\Http\Controllers\SchoolMonthlyAllUnitsReportExportController;
use App\Http\Controllers\SchoolMonthlyAllUnitsReportPdfController;
use App\Http\Controllers\SchoolMonthlyByLevelReportExportController;
use App\Http\Controllers\SchoolMonthlyByLevelReportPdfController;
use App\Http\Controllers\SchoolMonthlyReportExportController;
use App\Http\Controllers\SchoolMonthlyReportPdfController;
use App\Http\Controllers\StudentBillsPdfController;
use App\Http\Controllers\StudentTargetArrearsReportExportController;
use App\Http\Controllers\StudentTargetArrearsReportPdfController;
use App\Livewire\AcademicYearManagement;
use App\Livewire\AccountManagement;
use App\Livewire\BankManagement;
use App\Livewire\ClassPromotionRuleManagement;
use App\Livewire\Dashboard;
use App\Livewire\DaycareBulkUpdate;
use App\Livewire\DaycareDailyReport;
use App\Livewire\DaycareDetail;
use App\Livewire\DaycareImport;
use App\Livewire\DaycareManagement;
use App\Livewire\DaycarePaymentCreate;
use App\Livewire\DaycarePaymentEdit;
use App\Livewire\DaycarePaymentEntry;
use App\Livewire\DaycarePaymentShow;
use App\Livewire\PaymentCorrection;
use App\Livewire\PaymentCreate;
use App\Livewire\PaymentEdit;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentRateManagement;
use App\Livewire\PaymentShow;
use App\Livewire\PaymentTypeManagement;
use App\Livewire\ProspectivePaymentCreate;
use App\Livewire\ProspectivePaymentEdit;
use App\Livewire\ProspectivePaymentShow;
use App\Livewire\ProspectivePaymentWorkspace;
use App\Livewire\ProspectiveStudentBulkUpdate;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\ProspectiveStudentImport;
use App\Livewire\ProspectiveStudentManagement;
use App\Livewire\SchoolClassManagement;
use App\Livewire\SchoolDailyReport;
use App\Livewire\StudentBulkUpdate;
use App\Livewire\StudentDetail;
use App\Livewire\StudentExamEligibility;
use App\Livewire\StudentImport;
use App\Livewire\StudentManagement;
use App\Livewire\StudentManualPaymentEdit;
use App\Livewire\StudentPaymentSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', Dashboard::class)
    ->middleware('auth')
    ->name('dashboard');

Route::get('/siswa', StudentManagement::class)
    ->middleware('auth')
    ->name('siswa.index');

Route::get('/siswa/import', StudentImport::class)
    ->middleware('auth')
    ->name('siswa.import');

Route::get('/siswa/update', StudentBulkUpdate::class)
    ->middleware('auth')
    ->name('siswa.update');

Route::get('/siswa/kelayakan-kartu-ujian', StudentExamEligibility::class)
    ->middleware('auth')
    ->name('siswa.exam-eligibility');

Route::get('/siswa/{student}', StudentDetail::class)
    ->middleware('auth')
    ->name('siswa.show');

Route::get('/siswa/{student}/pembayaran', StudentPaymentSettings::class)
    ->middleware('auth')
    ->name('siswa.payment-settings');

Route::get('/siswa/{student}/tagihan/cetak-pdf', StudentBillsPdfController::class)
    ->middleware('auth')
    ->name('siswa.bills.pdf');

Route::get('/calon-siswa', ProspectiveStudentManagement::class)
    ->middleware('auth')
    ->name('calon-siswa.index');

Route::get('/calon-siswa/import', ProspectiveStudentImport::class)
    ->middleware('auth')
    ->name('calon-siswa.import');

Route::get('/calon-siswa/update', ProspectiveStudentBulkUpdate::class)
    ->middleware('auth')
    ->name('calon-siswa.update');

Route::get('/calon-siswa/{prospectiveStudent}', ProspectiveStudentDetail::class)
    ->middleware('auth')
    ->name('calon-siswa.show');

Route::get('/daycare', DaycareManagement::class)
    ->middleware('auth')
    ->name('daycare.index');

Route::get('/daycare/import', DaycareImport::class)
    ->middleware('auth')
    ->name('daycare.import');

Route::get('/daycare/update', DaycareBulkUpdate::class)
    ->middleware('auth')
    ->name('daycare.update');

Route::get('/daycare/pembayaran', DaycarePaymentEntry::class)
    ->middleware('auth')
    ->name('daycare.payment.entry');

Route::get('/daycare/laporan', DaycareDailyReport::class)
    ->middleware('auth')
    ->name('daycare.report.daily');

Route::get('/daycare/laporan/harian.xlsx', DaycareDailyReportExportController::class)
    ->middleware('auth')
    ->name('daycare.report.daily.export');

Route::get('/daycare/laporan/harian/pdf', DaycareDailyReportPdfController::class)
    ->middleware('auth')
    ->name('daycare.report.daily.pdf');

Route::get('/daycare/laporan/harian/riwayat.pdf', DaycareDailyReportHistoryPdfController::class)
    ->middleware('auth')
    ->name('daycare.report.daily.riwayat.pdf');

Route::get('/daycare/laporan/bulanan.xlsx', DaycareMonthlyReportExportController::class)
    ->middleware('auth')
    ->name('daycare.report.monthly.export');

Route::get('/daycare/laporan/bulanan/pdf', DaycareMonthlyReportPdfController::class)
    ->middleware('auth')
    ->name('daycare.report.monthly.pdf');

Route::get('/daycare/{child}/pembayaran', DaycarePaymentCreate::class)
    ->middleware('auth')
    ->name('daycare.payment.create');

Route::get('/daycare/pembayaran/{payment}/edit', DaycarePaymentEdit::class)
    ->middleware('auth')
    ->name('daycare.payment.edit');

Route::get('/daycare/pembayaran/{payment}/cetak/pdf', [ReceiptPrintController::class, 'daycarePdf'])
    ->middleware('auth')
    ->name('daycare.payment.pdf');

Route::get('/daycare/pembayaran/{payment}/cetak', [ReceiptPrintController::class, 'daycare'])
    ->middleware('auth')
    ->name('daycare.payment.print');

Route::get('/daycare/pembayaran/{payment}', DaycarePaymentShow::class)
    ->middleware('auth')
    ->name('daycare.payment.show');

Route::get('/daycare/{child}', DaycareDetail::class)
    ->middleware('auth')
    ->name('daycare.show');

Route::get('/kelas', SchoolClassManagement::class)
    ->middleware('auth')
    ->name('kelas.index');

Route::get('/tahun-ajaran', AcademicYearManagement::class)
    ->middleware('auth')
    ->name('tahun-ajaran.index');

Route::get('/aturan-kenaikan-kelas', ClassPromotionRuleManagement::class)
    ->middleware('auth')
    ->name('aturan-kenaikan-kelas.index');

Route::get('/bank', BankManagement::class)
    ->middleware('auth')
    ->name('bank.index');

Route::get('/jenis-pembayaran', PaymentTypeManagement::class)
    ->middleware('auth')
    ->name('jenis-pembayaran.index');

Route::get('/tarif-pembayaran', PaymentRateManagement::class)
    ->middleware('auth')
    ->name('tarif-pembayaran.index');

Route::get('/pembayaran', PaymentIndex::class)
    ->middleware('auth')
    ->name('pembayaran.index');

Route::get('/pembayaran/tambah', PaymentCreate::class)
    ->middleware('auth')
    ->name('pembayaran.create');

Route::get('/pembayaran/calon-siswa/{prospectiveStudent}', ProspectivePaymentWorkspace::class)
    ->middleware('auth')
    ->name('pembayaran.prospective.workspace');

Route::get('/pembayaran/calon-siswa/{prospectiveStudent}/pembayaran', ProspectivePaymentCreate::class)
    ->middleware('auth')
    ->name('pembayaran.prospective.create');

Route::get('/pembayaran/calon-siswa/pembayaran/{payment}/cetak/pdf', [ReceiptPrintController::class, 'prospectivePdf'])
    ->middleware('auth')
    ->name('pembayaran.prospective.pdf');

Route::get('/pembayaran/calon-siswa/pembayaran/{payment}/cetak', [ReceiptPrintController::class, 'prospective'])
    ->middleware('auth')
    ->name('pembayaran.prospective.print');

Route::get('/pembayaran/calon-siswa/pembayaran/{payment}', ProspectivePaymentShow::class)
    ->middleware('auth')
    ->name('pembayaran.prospective.show');

Route::get('/pembayaran/calon-siswa/pembayaran/{payment}/edit', ProspectivePaymentEdit::class)
    ->middleware('auth')
    ->name('pembayaran.prospective.edit');

Route::get('/pembayaran/{payment}/manual/edit', StudentManualPaymentEdit::class)
    ->middleware('auth')
    ->name('pembayaran.manual.edit');

Route::get('/pembayaran/{payment}/cetak/pdf', [ReceiptPrintController::class, 'studentPdf'])
    ->middleware('auth')
    ->name('pembayaran.pdf');

Route::get('/pembayaran/{payment}/cetak', [ReceiptPrintController::class, 'student'])
    ->middleware('auth')
    ->name('pembayaran.print');

Route::get('/pembayaran/{id}', PaymentShow::class)
    ->middleware('auth')
    ->name('pembayaran.show');

Route::get('/pembayaran/{id}/koreksi', PaymentCorrection::class)
    ->middleware('auth')
    ->name('pembayaran.koreksi');

Route::get('/pembayaran/{id}/edit', PaymentEdit::class)
    ->middleware('auth')
    ->name('pembayaran.edit');

Route::get('/laporan', SchoolDailyReport::class)
    ->middleware('auth')
    ->name('laporan.index');

Route::get('/laporan/harian.xlsx', SchoolDailyReportExportController::class)
    ->middleware('auth')
    ->name('laporan.harian.export');

Route::get('/laporan/harian/pdf', SchoolDailyReportPdfController::class)
    ->middleware('auth')
    ->name('laporan.harian.pdf');

Route::get('/laporan/harian/riwayat.pdf', SchoolDailyReportHistoryPdfController::class)
    ->middleware('auth')
    ->name('laporan.harian.riwayat.pdf');

Route::get('/laporan/bulanan.xlsx', SchoolMonthlyReportExportController::class)
    ->middleware('auth')
    ->name('laporan.bulanan.export');

Route::get('/laporan/bulanan/pdf', SchoolMonthlyReportPdfController::class)
    ->middleware('auth')
    ->name('laporan.bulanan.pdf');

Route::get('/laporan/per-jenjang.xlsx', SchoolMonthlyByLevelReportExportController::class)
    ->middleware('auth')
    ->name('laporan.jenjang.export');

Route::get('/laporan/per-jenjang.pdf', SchoolMonthlyByLevelReportPdfController::class)
    ->middleware('auth')
    ->name('laporan.jenjang.pdf');

Route::get('/laporan/seluruh-unit.xlsx', SchoolMonthlyAllUnitsReportExportController::class)
    ->middleware('auth')
    ->name('laporan.seluruh-unit.export');

Route::get('/laporan/seluruh-unit.pdf', SchoolMonthlyAllUnitsReportPdfController::class)
    ->middleware('auth')
    ->name('laporan.seluruh-unit.pdf');

Route::get('/laporan/rekap-bank.xlsx', SchoolBankRecapExportController::class)
    ->middleware('auth')
    ->name('laporan.bank.export');

Route::get('/laporan/rekap-bank/pdf', SchoolBankRecapPdfController::class)
    ->middleware('auth')
    ->name('laporan.bank.pdf');

Route::get('/laporan/target-tunggakan.xlsx', StudentTargetArrearsReportExportController::class)
    ->middleware('auth')
    ->name('laporan.target.export');

Route::get('/laporan/target-tunggakan.pdf', StudentTargetArrearsReportPdfController::class)
    ->middleware('auth')
    ->name('laporan.target.pdf');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/pengaturan/akun', AccountManagement::class)
    ->middleware(['auth', 'can:manage-accounts'])
    ->name('akun.index');

require __DIR__.'/auth.php';
