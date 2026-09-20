# PROJECT_CONTEXT.md — Annur Management

Dokumen konteks proyek. Dibuat dari audit langsung terhadap isi repo, bukan dari asumsi.
Memperbarui dokumen ini setiap kali ada keputusan arsitektur/domain baru.

- Repo resource: `C:\laragon\www\annur_management` (Windows) / lokal macOS
- Framework: **Laravel 13** (`laravel/framework ^13.17`), PHP 8.3+
- UI: **Livewire 3** (+ `livewire/blaze`), **Tailwind 4**, Vite
- Exports: **OpenSpout** (XLSX), **barryvdh/laravel-dompdf** (PDF)
- Test: **Pest**, formatter: Pint, static analysis: PHPStan (`types:check`)

> Catatan singkat: proyek ini adalah aplikasi **manajemen pembayaran sekolah
> (SPP/tagihan) + Daycare** dengan arsitektur :fire: 						         
> Kunci: **Satu file rute tunggal `routes/web.php`** yang semuanya GET (rendering
> penuh via Livewire) — **tidak ada rute POST/PUT/DELETE**; semua mutasi melalui
> action Livewire / controller export-PDF/Excel. Nama rute & komponen beredar
> memakai istilah Indonesia.

---

## 1. Ringkasan Domain

Aplikasi "Annur Management" mengelola:

1. **Registrasi & data siswa** (biodata, foto, kelas, jenjang TK/SD/SMP/SMA,
   status, entry date). Termasuk import massal dari XLSX dan update massal.
2. **Buku tagihan (billbook)** — pembuatan tagihan bulanan (SPP), tahunan
   (Uang Pangkal/Uang Buku), dan sekali-bayar per siswa, per periode bulan
   dan per tahun ajaran. Sistem ini idempotent (tidak membuat tagihan ganda).
3. **Cabang Daycare (TPA)** — data anak, pembayaran daycare, laporan harian
   & bulanan khusus daycare.
4. **Pembayaran & pengeluaran kas** — pencatatan pembayaran tagihan siswa,
   pembayaran manual, pencatatan dari siswa sendiri, koreksi pembayaran
   (dengan log), pembatalan, dan penerbitan kwitansi/cetak PDF.
5. **Laporan sekolah** — rekap harian, bulanan, per-jenjang (SD/SMP/SMA),
   seluruh unit, rekap bank, dan laporan target tunggakan — semua punya
   versi XLSX & PDF.
6. **Master data & pengaturan** — bank, jenis pembayaran, tarif pembayaran,
   kelas, tahun ajaran, akun pengguna.

---

## 2. Arsitektur File

### 2.1 Routing (`routes/web.php`) — SEMUA GET

Semua rute web didaftarkan di satu file dan semuanya `GET`; komponen Livewire
bertindak sebagai halaman (Full-Page Component). Tidak ada rute state-mutating
di level HTTP — mutasi terjadi lewat aksi Livewire di dalam komponen.

Kelompok rute (awalan URL → nama rute):

| URL | Livewire / Controller | Nama rute |
|---|---|---|
| `/` | welcome view | — |
| `/dashboard` | `Dashboard` | `dashboard` |
| `/siswa` | `StudentManagement` | `siswa.index` |
| `/siswa/import` | `StudentImport` | `siswa.import` |
| `/siswa/update` | `StudentBulkUpdate` | `siswa.update` |
| `/siswa/kelayakan-kartu-ujian` | `StudentExamEligibility` | `siswa.exam-eligibility` |
| `/siswa/{student}` | `StudentDetail` | `siswa.show` |
| `/siswa/{student}/pembayaran` | `StudentPaymentSettings` | `siswa.payment-settings` |
| `/siswa/{student}/tagihan/cetak-pdf` | `StudentBillsPdfController` | `siswa.bills.pdf` |
| `/daycare` | `DaycareManagement` | `daycare.index` |
| `/daycare/import` | `DaycareImport` | `daycare.import` |
| `/daycare/update` | `DaycareBulkUpdate` | `daycare.update` |
| `/daycare/pembayaran` | `DaycarePaymentEntry` | `daycare.payment.entry` |
| `/daycare/laporan` | `DaycareDailyReport` | `daycare.report.daily` |
| `/daycare/laporan/harian.xlsx` | `DaycareDailyReportExportController` | `daycare.report.daily.export` |
| `/daycare/laporan/harian/pdf` | `DaycareDailyReportPdfController` | `daycare.report.daily.pdf` |
| `/daycare/laporan/bulanan.xlsx` | `DaycareMonthlyReportExportController` | `daycare.report.monthly.export` |
| `/daycare/laporan/bulanan/pdf` | `DaycareMonthlyReportPdfController` | `daycare.report.monthly.pdf` |
| `/daycare/{child}/pembayaran` | `DaycarePaymentCreate` | `daycare.payment.create` |
| `/daycare/pembayaran/{payment}/edit` | `DaycarePaymentEdit` | `daycare.payment.edit` |
| `/daycare/pembayaran/{payment}/cetak/pdf` | `ReceiptPrintController::daycarePdf` | `daycare.payment.pdf` |
| `/daycare/pembayaran/{payment}/cetak` | `ReceiptPrintController::daycare` | `daycare.payment.print` |
| `/daycare/pembayaran/{payment}` | `DaycarePaymentShow` | `daycare.payment.show` |
| `/daycare/{child}` | `DaycareDetail` | `daycare.show` |
| `/kelas` | `SchoolClassManagement` | `kelas.index` |
| `/tahun-ajaran` | `AcademicYearManagement` | `tahun-ajaran.index` |
| `/bank` | `BankManagement` | `bank.index` |
| `/jenis-pembayaran` | `PaymentTypeManagement` | `jenis-pembayaran.index` |
| `/tarif-pembayaran` | `PaymentRateManagement` | `tarif-pembayaran.index` |
| `/pembayaran` | `PaymentIndex` | `pembayaran.index` |
| `/pembayaran/tambah` | `PaymentCreate` | `pembayaran.create` |
| `/pembayaran/siswa/{student}/manual` | `StudentManualPaymentCreate` | `pembayaran.manual.create` |
| `/pembayaran/{payment}/manual/edit` | `StudentManualPaymentEdit` | `pembayaran.manual.edit` |
| `/pembayaran/{payment}/cetak/pdf` | `ReceiptPrintController::studentPdf` | `pembayaran.pdf` |
| `/pembayaran/{payment}/cetak` | `ReceiptPrintController::student` | `pembayaran.print` |
| `/pembayaran/{id}` | `PaymentShow` | `pembayaran.show` |
| `/pembayaran/{id}/koreksi` | `PaymentCorrection` | `pembayaran.koreksi` |
| `/pembayaran/{id}/edit` | `PaymentEdit` | `pembayaran.edit` |
| `/laporan` | `SchoolDailyReport` | `laporan.index` |
| `/laporan/harian.xlsx` | `SchoolDailyReportExportController` | `laporan.harian.export` |
| `/laporan/harian/pdf` | `SchoolDailyReportPdfController` | `laporan.harian.pdf` |
| `/laporan/bulanan.xlsx` | `SchoolMonthlyReportExportController` | `laporan.bulanan.export` |
| `/laporan/bulanan/pdf` | `SchoolMonthlyReportPdfController` | `laporan.bulanan.pdf` |
| `/laporan/per-jenjang.xlsx` | `SchoolMonthlyByLevelReportExportController` | `laporan.jenjang.export` |
| `/laporan/per-jenjang.pdf` | `SchoolMonthlyByLevelReportPdfController` | `laporan.jenjang.pdf` |
| `/laporan/seluruh-unit.xlsx` | `SchoolMonthlyAllUnitsReportExportController` | `laporan.seluruh-unit.export` |
| `/laporan/seluruh-unit.pdf` | `SchoolMonthlyAllUnitsReportPdfController` | `laporan.seluruh-unit.pdf` |
| `/laporan/rekap-bank.xlsx` | `SchoolBankRecapExportController` | `laporan.bank.export` |
| `/laporan/rekap-bank/pdf` | `SchoolBankRecapPdfController` | `laporan.bank.pdf` |
| `/laporan/target-tunggakan.xlsx` | `StudentTargetArrearsReportExportController` | `laporan.target.export` |
| `/laporan/target-tunggakan.pdf` | `StudentTargetArrearsReportPdfController` | `laporan.target.pdf` |
| `/pengaturan/akun` | `AccountManagement` | `akun.index` |
| `/profile` | `ProfileController` | `profile.*` |
| auth routes | `auth.php` | `login/logout/register/...` |

Catatan penting: rute laporan dengan `>=` cukup banyak; semuanya dibungkus
`middleware('auth')`. Halaman akun juga `can:manage-accounts`.

---

## 3. Model & Skema (database)

### Daftar model `app/Models`
`AcademicYear`, `Bank`, `BillAdjustment`, `DaycareChild`, `DaycarePayment`,
`DaycarePaymentDetail`, `Payment`, `PaymentCorrectionLog`, `PaymentDetail`,
`PaymentRate`, `PaymentType`, `PaymentTypeSchoolLevel`, `SchoolClass`, `Setting`,
`Student`, `StudentAcademicEnrollment`, `StudentBill`, `StudentEligibilityConfig`,
`StudentExam`, `StudentExamRequirement`, `StudentPaymentSetting`, `User`.

### Skema inti (migrations `database/migrations`)

- **`students`** — `nis` (unik), `nama_lengkap`, `nama_panggilan`, `class_id`
  (FK `school_classes`, restrictOnDelete), `jenis_kelamin` (enum L/P),
  `alamat`, nama & telepon ayah/ibu, `tempat_lahir`, `tanggal_lahir` (nullable,
  biodata boleh kosong), `entry_date`, `foto` (nullable), `status`.
  Kolom `school_level` dihitung dari kelas, bukan kolom terpisah.
- **`school_classes`** — id, `name` (rombel, contoh "XII A IPA"), `level`
  (unsignedTinyInteger). Relasi `studyLevel` utk menentukan jenjang.
- **`payment_types`** — `name`, `description`, `is_active`, `is_auto_enrolled`,
  `is_required`.
- **`payment_rates`** (menggantikan `spp_rates`) — `payment_type_id`,
  `class_level` (tingkat numerik — **bukan** class/rombel), `amount`,
  `is_monthly` (deprecated → diturunkan dari `billing_frequency`),
  `billing_frequency` (enum), `effective_from`, `effective_until` nullable.
  Rate SPP **diresolusi per class_level**, bukan per rombel.
- **`payment_type_school_levels`** — mapping jenis pembayaran aktif per jenjang
  (`school_level`, `is_required`, `is_active`).
- **`student_payment_settings`** — per-siswa: `student_id`, `payment_type_id`,
  `is_active`, `started_at`, `ended_at`, `custom_amount` (nullable; bisa
  menimpa nominal rate default).
- **`student_bills`** — `student_id`, `payment_type_id`, `amount`,
  `period_month`, `period_year`, `academic_year`, `billing_frequency`
  (monthly/yearly/one_time), `due_date`, `updated_by` (nullable).
- **`payments`** — `receipt_number`, `payment_kind` (bill|manual),
  `student_id`, `bank_id`, `payment_date`, `total_amount`, `payment_method`,
  `receipt`, `description`, `status` (active|cancelled), `cancelled_by`,
  `cancelled_at`, `cancellation_reason`, `created_by`.
- **`payment_details`** — `payment_id`, `bill_id` (nullable), `payment_type_id`,
  `period_month`, `period_year`, `academic_year` (nullable), `amount`,
  `description`.
- **`bill_adjustments`** — `bill_id`, `type` (discount), `amount`, `reason`,
  `created_by`.
- **`payment_correction_logs`** — log untuk aksi koreksi pembayaran.
- **`academic_years`** (+ `student_academic_enrollments` + `promotion_processed_at`)
  — tahun ajaran; `start_date`, `end_date`, `is_active`, status promosi.
- **`daycare_children`** — biodata anak daycare (kolom biodata opsional/nullable).
- **`daycare_payments`, `daycare_payment_details`** (+ `receipt_number`).
- **`banks`** — nama, akun (dengan `type` & nullable account_details).
- **`settings`** — key/value global (mis. `billbook.start_month`).
- **`users`** — dengan `role` & `is_active` (migration
  `update_users_roles_and_add_is_active`).

---

## 4. Domain Flow — Billbook / Tagihan (`app/Services/BillGenerationService.php`)

Ini jantung aplikasi. Fungsi inti:

- `generateUntil(Student $student, ?$untilDate)` → loop bulan dari periode
  sekarang sampai target; gabungkan tagihan bulanan + tahunan.
- `generateForStudent(Student $student, ?$targetDate, bool $includeYearly)`
  → buat tagihan untuk satu titik waktu; idempotent.
- `resolveRate(PaymentType $type, int $level, $targetDate)` → pilih
  `payment_rates` yang `effective_from <= target`, `effective_until` NULL atau
  `>= target`; jika beberapa cocok, ambil `effective_from` terbaru.
- `resolvedAmountFor(StudentPaymentSetting $setting, PaymentRate $rate)` →
  `custom_amount` siswa jika diisi, selain itu nominal rate.
- `createBill(...)` → guard unic: semua pembuatan bill lewat satu pintu:
  - guard `isBillbookType` (jenis otomatis aktif + mapping jenjang aktif);
  - **Monthly**: dedup per `student+type+period_month×period_year`;
  - **Yearly**: satu bill per `academic_year` (dedup per tahun ajaran);
  - **One-time**: dedup seumur hidup per `student+type`.
- `getMonthlyGenerationPreview()`, `generateYearlyBillForAcademicYear()`,
  `generateYearlyBillsForRange()` → untuk tahunan per tahun ajaran.

Support: `app/Support/BillbookPeriod.php` — periode buku tagihan dimulai dari
`settings('billbook.start_month')` dan **selalu berakhir Juni**; `months()` dan
`endDateFor()` dipakai semua laporan & generasi.

### Keputusan arsitektur penting
- **Idempoten**: tagihan tidak pernah dibuat ganda (guard application-level
  + unique index di tabel).
- **Frekuensi**: enum `BillFrequency` (`monthly|yearly|one_time`); kolom usang
  `is_monthly` dipertahankan sebagai kompatibilitas, sinkron otomatis lewat
  mutator `setBillingFrequencyAttribute` / `setIsMonthlyAttribute`.
- **Billing per jenjang**: `payment_type_school_levels` menentukan jenis mana
  yang otomatis untuk SD/SMP/SMA/TK; rate diresolusi via `class_level`.
- **Overrides per siswa**: `student_payment_settings.custom_amount`.

---

## 5. Pembayaran & Koreksi

`app/Models/Payment.php`:
- `isBillPayment()` (kind=bread) vs `isManualPayment()`.
- `getSettlementStatusAttribute()` → `lunas` / `tunggakan` / `tercatat`.
- `getStatusLabelAttribute()` → `Dibatalkan` / `Lunas` / `Tunggakan` / `Tercatat`.
- `getDetailDisplayAttribute()` → ringkasan deskripsi detail pembayaran untuk
  Dashboard & Index (pakai logika: 1 detail → deskripsi utuh, 2 → `a + b`,
  >2 → `a + N lainnya`).
- relasi: `student()`, `bank()`, `user()` (created_by), `cancelledBy()`,
  `details()`, `correctionLogs()`.

Koreksi: halaman `PaymentCorrection`, `PaymentEdit`, `PaymentCancellationService`,
`StudentManualPayment*` — semua lewat Livewire + service. `PaymentCorrectionLog`
mencatat jejak koreksi. `BillAdjustment` untuk diskon pada bill.

---

## 6. Import & Bulk Update

- **Siswa**: `StudentImport`, `StudentBulkUpdate` (Livewire) + service
  `StudentImportService` / `StudentBulkUpdateService` dengan pembaca spreadsheet.
- **Daycare**: `DaycareImport`, `DaycareBulkUpdate` + service
  `DaycareImportService` / `DaycareBulkUpdateService`.
- Pilihan: import kolom wajib vs opsional (biodata nullable).

---

## 7. Laporan (XLSX + PDF)

Repositori controller export/PDF di `app/Http/Controllers` (pola `*ExportController`
untuk OpenSpout, `*PdfController` untuk DomPDF), diarahkan dari `routes/web.php`.

Jenis laporan:
- Harian sekolah & daycare
- Bulanan sekolah (keseluruhan), per-jenjang (by level), seluruh unit, daycare
- Rekap bank
- Target tunggakan (target arrears)
- Kwitansi/kartu tagihan siswa (StudentBills PDF)

Service pendukung: `app/Services/*ReportService.php` + `app/Services/SchoolDailyReportSpreadsheet.php`
dll. Ada `ReceiptPrintController` untuk kwitansi (student + daycare), `ReceiptPdfController`.

---

## 8. Dashboard & Operasional

`app/Livewire/Dashboard.php` — ringkasan (pembayaran terbaru, ringkasan
tunggakan, laporan). Ada `DashboardTargetArrearsSummary` dsb yang ditest.

---

## 9. Testing (Pest)

Proyek **test-heavy**: hampir seluruh fitur punya test Feature di `tests/Feature`.
Grup besar:
- **Billbook**: `BillGenerationServiceTest`, `BillGenerateUntilTest`,
  `BillGenerationBackfillRegressionTest`, `StudentBillbookAutoGenerationTest`,
  `MonthlyBillingTest`, `YearlyBillingTest`, `StudentBillManualAddTest`,
  `BillAdjustmentPaymentTest`, `OneTimeBillLifetimeNoDuplicateTest`,
  `FutureStudentMonthlyGenerationTest`, `InitialStudentJulyBillTest`,
  `AcademicYearSequencingTest`.
- **Pembayaran**: `PaymentCreateBillsTest`, `PaymentEditTest`, `PaymentEditAmountInputTest`,
  `PaymentEditAmountSyncTest`, `PaymentEditGroupingTest`, `PaymentShowTest`,
  `PaymentIndexTest`, `PaymentBillSelectionTest`, `PaymentSettlementStatusTest`,
  `PaymentCorrectionCancellationTest`, `PaymentCancellationFinancialTest`,
  `StudentManualPaymentTest`, `StudentManualPaymentEditTest`,
  `StudentReceiptAuthorizationTest`, `StudentDetailBillsTest`,
  `StudentDetailGroupedBillsTest`, `StudentDetailHistoryVisibilityTest`,
  `StudentDetailSummaryCardsTest`, `StudentDetailSummaryCategoryFilterTest`,
  `TableNumberColumnTest`, `Dashboard*`.
- **Daycare**: `DaycareManagementTest`, `DaycareImportTest`, `DaycareBulkUpdateTest`,
  `DaycarePaymentEntryTest`, `DaycarePaymentTest`, `DaycarePaymentEditTest`,
  `DaycareDailyReportTest`, `DaycareMonthlyReportTest`, `DaycareMonthlyPerTanggalReportTest`,
  `DaycareDailyReportPdfTest`, `DaycareMonthlyReportPdfTest`, `DaycareReceiptTest`,
  `DaycareReceiptAuthorizationTest`, `DaycareSeparationTest`.
- **Laporan**: `SchoolDailyReportTest`, `SchoolMonthlyReportTest`,
  `SchoolMonthlyByLevelReportTest`, `SchoolMonthlyAllUnitsReportTest`,
  `SchoolBankRecapTest`, `StudentTargetArrearsReportTest`, `SchoolDailyReportPdfTest`,
  `SchoolMonthlyReportPdfTest`, `SchoolMonthlyByLevelReportPdfTest`,
  `SchoolMonthlyAllUnitsReportPdfTest`, `SchoolBankRecapPdfTest`, `SchoolMonthlyReportXlsxTest`,
  `SchoolMonthlyByLevelReportXlsxTest`, `StudentBillsPdfTest`, `ReportCreatorSignatureTest`.
- **Master data**: `BankManagementTest`, `PaymentTypeManagementTest`,
  `PaymentRateManagementTest`, `SchoolClassManagementTest`, `AccountManagementTest`,
  `StudentManagementTest`, dll.
- **Eligibility**: `StudentExamEligibilityTest`, `StudentExamEligibilityFilterRestoreTest`,
  `StudentExamEligibilityPageTest`.
- **Migrasi/Seeder**: `MasterDataSeederTest`, `SchoolDataSeederTest`.

`tests/Pest.php`, `tests/TestCase.php`, `tests/Unit/SchoolReportDocumentTest.php`.

---

## 10. Pengaturan, Kewenangan & Keamanan

- `app/Policies` mungkin; gate `manage-accounts` dipakai rute akun.
- `Setting::get/set` untuk konfigurasi global (`billbook.start_month`).
- Semua rute `middleware('auth')`; halaman akun `can:manage-accounts`.

---

## 11. Fitur yang Sudah Selesai (ringkas per modul saat state sekarang)

1. **Siswa**: manajemen, detail, import xlsx, bulk update, settings pembayaran
   per siswa, cetak tagihan PDF.
2. **Billbook**: generasi bulanan/tahunan/sekali bayar idempotent, backfill,
   preview generasi, dedup, tahun ajaran.
3. **Pembayaran**: index, create (otomatis dari bill), manual, edit, koreksi
   + log, pembatalan, kwitansi PDF, status lunas/tunggakan.
4. **Daycare**: manajemen anak, import/update massal, pembayaran daycare,
   laporan harian & bulanan (xlsx+pdf), kwitansi daycare.
5. **Laporan sekolah**: harian, bulanan, per-jenjang, seluruh unit, rekap bank,
   target tunggakan (semua punya xlsx & pdf).
6. **Master data**: bank, jenis pembayaran, tarif, kelas, tahun ajaran, akun.
7. **Autentikasi & profil** (Breeze-style Livewire).

## 12. Konvensi Tim & Perintah

- Perubahan kode PHP wajib diformat: `vendor/bin/pint --format agent`.
- Test: `php artisan test --compact` atau `--filter=...`.
- Lint + types hanya via CI (`ci:check`), tidak manual set.
- Tidak membuat file dokumentasi tanpa diminta; **file ini diminta** (PROJECT_CONTEXT).

---

*Dokumen ini sebagian dihasilkan dari audit otomatis terhadap isi repo. Tabel
rute/model/publik dirangkum dari sumber yang sebenarnya (`routes/web.php`,
`app/Models/*`, `app/Services/*`, `database/migrations/*`).*