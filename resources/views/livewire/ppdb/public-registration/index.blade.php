<div class="min-w-0">
    <section class="mb-5 px-1 text-white sm:mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-200">PPDB Online</p>
        <h1 class="mt-2 max-w-3xl text-2xl font-bold leading-tight tracking-tight sm:text-4xl">Formulir Pendaftaran Peserta Didik Baru</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base">Lengkapi enam langkah berikut. Data tersimpan setelah pendaftaran berhasil dikirim.</p>
    </section>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:rounded-3xl">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
            <div class="lg:hidden" aria-label="Kemajuan pendaftaran">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Langkah {{ $currentStep }} dari 6</p>
                        <p class="mt-0.5 truncate text-base font-semibold text-slate-950">{{ $stepTitles[$currentStep] }}</p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{{ $currentStep }}</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuemin="1" aria-valuemax="6" aria-valuenow="{{ $currentStep }}" aria-label="Langkah {{ $currentStep }} dari 6">
                    <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ ($currentStep / 6) * 100 }}%"></div>
                </div>
            </div>

            <ol class="hidden grid-cols-6 gap-3 lg:grid" aria-label="Tahapan pendaftaran">
                @foreach ($stepTitles as $stepNumber => $stepTitle)
                    <li class="min-w-0 text-center" @if ($currentStep === $stepNumber) aria-current="step" @endif>
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold {{ $currentStep >= $stepNumber ? 'border-primary bg-primary text-white' : 'border-slate-300 bg-white text-slate-500' }}">{{ $stepNumber }}</div>
                        <p class="mt-2 min-h-10 text-xs font-semibold leading-5 {{ $currentStep === $stepNumber ? 'text-primary' : 'text-slate-600' }}">{{ $stepTitle }}</p>
                        <div class="mt-2 h-1.5 rounded-full {{ $currentStep >= $stepNumber ? 'bg-blue-600' : 'bg-slate-200' }}"></div>
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($openAcademicYears->isEmpty())
            <div class="px-5 py-14 text-center sm:px-8">
                <span class="material-symbols-outlined text-6xl text-slate-400">event_busy</span>
                <h2 class="mt-4 text-xl font-semibold text-slate-950">Pendaftaran PPDB sedang ditutup</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">Belum ada tahun ajaran yang dibuka untuk pendaftaran online. Silakan kembali setelah periode pendaftaran dibuka.</p>
            </div>
        @else
            <div class="p-4 sm:p-6 lg:p-8">
                @php
                    $stepErrorKeys = match($currentStep) {
                        1 => ['academicYearId', 'schoolLevel', 'destinationGrade', 'applicantCategory', 'documents.bukti_transfer'],
                        2 => ['fullName', 'nickname', 'gender', 'birthplace', 'birthdate', 'asalSekolah', 'address'],
                        3 => ['father.name', 'father.phone', 'father.occupation', 'father.income_range', 'mother.name', 'mother.phone', 'mother.occupation', 'mother.income_range', 'guardian.name', 'guardian.phone', 'guardian.occupation', 'guardian.income_range'],
                        4 => ['siblings'],
                        5 => collect(\App\Enums\PpdbDocumentType::values())->map(fn($v) => 'documents.'.$v)->all(),
                        6 => ['consent', 'submission'],
                        default => [],
                    };
                    $hasStepErrors = $errors->hasAny($stepErrorKeys);
                @endphp
                @if ($hasStepErrors)
                    <div role="alert" aria-live="polite" class="mb-6 rounded-xl border border-red-300 bg-red-50 px-4 py-4 text-red-950" tabindex="-1" data-validation-summary>
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined shrink-0 text-red-700" aria-hidden="true">error</span>
                            <div class="min-w-0">
                                <p class="font-semibold">Ada data yang belum lengkap.</p>
                                <p class="mt-1 text-sm leading-5">Silakan periksa kolom yang ditandai dan lengkapi sebelum melanjutkan.</p>
                            </div>
                        </div>
                    </div>
                @elseif ($errors->any() && $currentStep < 6)
                    <div role="alert" aria-live="polite" class="mb-6 rounded-xl border border-red-300 bg-red-50 px-4 py-4 text-red-950" tabindex="-1">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined shrink-0 text-red-700" aria-hidden="true">error</span>
                            <div class="min-w-0">
                                <p class="font-semibold">Ada data yang belum lengkap.</p>
                                <p class="mt-1 text-sm leading-5">Silakan periksa kolom yang ditandai dan lengkapi sebelum melanjutkan.</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($currentStep === 1)
                    <section aria-labelledby="step-payment-title" data-step-section tabindex="-1" class="scroll-mt-4 outline-none sm:scroll-mt-8">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined rounded-xl bg-blue-100 p-2 text-blue-800">payments</span>
                            <div>
                                <h2 id="step-payment-title" class="text-xl font-semibold text-slate-950 sm:text-2xl">Bukti Pembayaran</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600">Pilih tujuan sekolah terlebih dahulu agar nominal biaya sesuai tarif yang berlaku.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label for="academic-year" class="block text-sm font-semibold text-slate-800">Tahun ajaran <span class="text-red-700" aria-hidden="true">*</span></label>
                                <select id="academic-year" wire:model.live="academicYearId" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 bg-white px-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('academicYearId')]) aria-invalid="{{ $errors->has('academicYearId') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('academicYearId') ? 'error-academicYearId' : '' }}">
                                    <option value="">Pilih tahun ajaran</option>
                                    @foreach ($openAcademicYears as $academicYear)
                                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                                    @endforeach
                                </select>
                                @error('academicYearId') <p id="error-academicYearId" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="school-level" class="block text-sm font-semibold text-slate-800">Jenjang sekolah <span class="text-red-700" aria-hidden="true">*</span></label>
                                <select id="school-level" wire:model.live="schoolLevel" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 bg-white px-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('schoolLevel')]) aria-invalid="{{ $errors->has('schoolLevel') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('schoolLevel') ? 'error-schoolLevel' : '' }}">
                                    <option value="">Pilih jenjang</option>
                                    @foreach ($gradeGroups as $level => $grades)
                                        <option value="{{ $level }}">{{ $level }}</option>
                                    @endforeach
                                </select>
                                @error('schoolLevel') <p id="error-schoolLevel" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="destination-grade" class="block text-sm font-semibold text-slate-800">Tingkat tujuan <span class="text-red-700" aria-hidden="true">*</span></label>
                                <select id="destination-grade" wire:model.live="destinationGrade" @disabled($schoolLevel === '') @class(['mt-2 h-12 w-full rounded-xl border-slate-300 bg-white px-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('destinationGrade')]) aria-invalid="{{ $errors->has('destinationGrade') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('destinationGrade') ? 'error-destinationGrade' : '' }}">
                                    <option value="">Pilih tingkat</option>
                                    @foreach ($gradeOptions as $grade => $gradeLabel)
                                        <option value="{{ $grade }}" @disabled(! in_array($grade, $configuredGrades, true))>{{ $gradeLabel }}{{ in_array($grade, $configuredGrades, true) ? '' : ' (belum tersedia)' }}</option>
                                    @endforeach
                                </select>
                                @error('destinationGrade') <p id="error-destinationGrade" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <fieldset @class(['rounded-2xl border p-4 sm:p-5', 'border-red-400 bg-red-50' => $errors->has('applicantCategory'), 'border-slate-200 bg-slate-50' => ! $errors->has('applicantCategory')]) aria-invalid="{{ $errors->has('applicantCategory') ? 'true' : 'false' }}" aria-describedby="applicant-category-help{{ $errors->has('applicantCategory') ? ' error-applicantCategory' : '' }}">
                                <legend class="px-1 text-sm font-semibold text-slate-950">Kategori Pendaftar <span class="text-red-700" aria-hidden="true">*</span></legend>
                                <p id="applicant-category-help" class="mt-1 text-sm leading-6 text-slate-600">Pilih kategori yang sesuai dengan kondisi calon siswa.</p>
                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach ($applicantCategories as $category)
                                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-slate-300 bg-white p-3 text-sm font-semibold text-slate-900 transition hover:border-blue-500 focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50">
                                            <input type="radio" name="applicantCategory" wire:model="applicantCategory" value="{{ $category->value }}" class="h-5 w-5 shrink-0 border-slate-400 text-blue-700 focus:ring-blue-700">
                                            <span>{{ $category->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('applicantCategory') <p id="error-applicantCategory" class="mt-2 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </fieldset>

                            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950 sm:p-5">
                                <h3 class="font-semibold">Siapa yang termasuk Keluarga Annur?</h3>
                                <p class="mt-2">Calon siswa termasuk Keluarga Annur apabila memenuhi minimal salah satu kriteria berikut:</p>
                                <ol class="mt-2 list-decimal space-y-2 pl-5">
                                    <li>Memiliki kakak atau adik yang sedang bersekolah di Annur atau merupakan alumni Annur.</li>
                                    <li>Calon siswa merupakan alumni Annur dari jenjang sebelumnya. Contohnya, lulusan SD Annur yang melanjutkan pendidikan ke SMP Annur.</li>
                                    <li>Berdomisili di RT 004/RW 009 atau RT 006/RW 009.</li>
                                </ol>
                                <h3 class="mt-4 font-semibold">Siapa yang termasuk Non-Keluarga Annur?</h3>
                                <p class="mt-2">Calon siswa yang tidak memenuhi satu pun kriteria Keluarga Annur di atas.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                                <p class="text-sm font-semibold text-blue-950">Biaya pendaftaran sesuai pilihan</p>
                                @if ($academicYearId === '' || $destinationGrade === '')
                                    <p class="mt-2 text-sm leading-6 text-blue-900">Pilih tahun ajaran, jenjang, dan tingkat untuk melihat nominal yang benar.</p>
                                @elseif ($registrationFees->isEmpty())
                                    <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950">
                                        Tarif belum tersedia untuk pilihan ini. Jangan melakukan transfer sebelum menghubungi panitia PPDB.
                                    </div>
                                @else
                                    <dl class="mt-3 space-y-2">
                                        @foreach ($registrationFees as $fee)
                                            <div class="flex items-start justify-between gap-3 text-sm">
                                                <dt class="min-w-0 break-words text-slate-700">{{ $fee['payment_type']->name }}</dt>
                                                <dd class="shrink-0 font-semibold text-slate-950">Rp{{ number_format($fee['amount'], 0, ',', '.') }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-blue-200 pt-3">
                                        <span class="text-sm font-semibold text-slate-800">Total transfer</span>
                                        <span class="text-xl font-bold text-blue-900">Rp{{ number_format($registrationFeeTotal, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                <p class="mt-3 text-xs leading-5 text-slate-600">Tarif ditampilkan dari konfigurasi tagihan dan diperiksa kembali saat pendaftaran dikirim.</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                                <p class="text-sm font-semibold text-slate-950">Rekening tujuan transfer</p>
                                @if ($paymentBank)
                                    <p class="mt-3 text-sm font-medium text-slate-700">{{ $paymentBank->name }} / Bank Syariah Indonesia</p>
                                    <div class="mt-2 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-xs text-slate-500">Nomor rekening</p>
                                            <p id="ppdb-account-number" class="mt-0.5 break-all font-mono text-lg font-bold tracking-wide text-slate-950">{{ $paymentBank->account_number }}</p>
                                        </div>
                                        <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText(document.getElementById('ppdb-account-number').textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-blue-700 px-4 text-sm font-semibold text-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2">
                                            <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                            <span x-text="copied ? 'Tersalin' : 'Salin nomor'">Salin nomor</span>
                                        </button>
                                    </div>
                                    <p class="mt-3 text-xs text-slate-500">Atas nama</p>
                                    <p class="break-words text-sm font-semibold text-slate-950">{{ $paymentBank->account_name }}</p>
                                @elseif ($paymentBankAmbiguous)
                                    <p class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950">Rekening BSI tujuan belum dapat ditampilkan karena ditemukan lebih dari satu rekening yang cocok. Hubungi panitia PPDB.</p>
                                @else
                                    <p class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950">Rekening BSI tujuan belum tersedia. Hubungi panitia PPDB sebelum melakukan transfer.</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-6">
                            <p class="text-sm font-semibold text-slate-800">Bukti transfer <span class="text-red-700" aria-hidden="true">*</span></p>
                            <label for="payment-proof" @class(['mt-2 flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-6 text-center transition focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2', 'border-emerald-400 bg-emerald-50' => isset($documents['bukti_transfer']) && !$errors->has('documents.bukti_transfer'), 'border-red-500 bg-red-50' => $errors->has('documents.bukti_transfer'), 'border-slate-300 bg-slate-50 hover:border-blue-500 hover:bg-blue-50' => !isset($documents['bukti_transfer']) && !$errors->has('documents.bukti_transfer')]) aria-invalid="{{ $errors->has('documents.bukti_transfer') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('documents.bukti_transfer') ? 'error-documents.bukti_transfer' : '' }}">
                                <input id="payment-proof" type="file" wire:model="documents.bukti_transfer" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="sr-only">
                                <span class="material-symbols-outlined text-4xl {{ $errors->has('documents.bukti_transfer') ? 'text-red-700' : (isset($documents['bukti_transfer']) ? 'text-emerald-700' : 'text-blue-800') }}">{{ $errors->has('documents.bukti_transfer') ? 'error' : (isset($documents['bukti_transfer']) ? 'check_circle' : 'cloud_upload') }}</span>
                                @if (isset($documents['bukti_transfer']) && is_object($documents['bukti_transfer']) && method_exists($documents['bukti_transfer'], 'getClientOriginalName'))
                                    <span class="mt-2 max-w-full break-all text-sm font-semibold {{ $errors->has('documents.bukti_transfer') ? 'text-red-900' : 'text-emerald-900' }}">{{ $documents['bukti_transfer']->getClientOriginalName() }}</span>
                                    <span class="mt-3 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm">Ganti File</span>
                                @else
                                    <span class="mt-2 text-sm font-semibold text-slate-950">Pilih bukti transfer</span>
                                    <span class="mt-1 text-xs leading-5 text-slate-600">PDF, JPG, JPEG, atau PNG · Maksimal 2 MB</span>
                                    <span class="mt-3 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Pilih File</span>
                                @endif
                                <span wire:loading wire:target="documents.bukti_transfer" class="mt-3 text-sm font-semibold text-blue-800">Mengunggah file...</span>
                            </label>
                            @error('documents.bukti_transfer') <p id="error-documents.bukti_transfer" class="mt-2 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            <p class="mt-3 rounded-xl bg-slate-100 p-3 text-sm leading-6 text-slate-700">Bukti transfer akan diperiksa oleh petugas. Mengunggah bukti tidak otomatis menandai tagihan sebagai lunas.</p>
                        </div>
                    </section>
                @elseif ($currentStep === 2)
                    <section aria-labelledby="step-student-title" data-step-section tabindex="-1" class="scroll-mt-4 outline-none sm:scroll-mt-8">
                        <h2 id="step-student-title" class="text-xl font-semibold text-slate-950 sm:text-2xl">Data Calon Siswa</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Isi identitas, alamat, dan data fisik/kesehatan calon siswa sesuai dokumen resmi.</p>

                        <div class="mt-6 space-y-6">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                                <h3 class="text-base font-semibold text-slate-950">Identitas Siswa</h3>
                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="fullName" class="block text-sm font-semibold text-slate-800">Nama lengkap <span class="text-red-700">*</span></label>
                                        <input id="fullName" type="text" wire:model="fullName" autocomplete="name" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('fullName')]) aria-invalid="{{ $errors->has('fullName') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('fullName') ? 'error-fullName' : '' }}">
                                        @error('fullName') <p id="error-fullName" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="nickname" class="block text-sm font-semibold text-slate-800">Nama panggilan</label>
                                        <input id="nickname" type="text" wire:model="nickname" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('nickname')]) aria-invalid="{{ $errors->has('nickname') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('nickname') ? 'error-nickname' : '' }}">
                                        @error('nickname') <p id="error-nickname" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <fieldset @class(['rounded-xl p-1 -m-1 md:col-span-2', 'ring-1 ring-red-300' => $errors->has('gender')]) aria-invalid="{{ $errors->has('gender') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('gender') ? 'error-gender' : '' }}">
                                        <legend class="px-1 text-sm font-semibold text-slate-800">Jenis kelamin <span class="text-red-700">*</span></legend>
                                        <div class="mt-2 grid grid-cols-2 gap-3">
                                            @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $genderValue => $genderLabel)
                                                <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-800 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50 {{ $errors->has('gender') ? 'border-red-400' : '' }}">
                                                    <input type="radio" name="gender" wire:model="gender" value="{{ $genderValue }}" class="h-5 w-5 border-slate-400 text-blue-700 focus:ring-blue-700"> {{ $genderLabel }}
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('gender') <p id="error-gender" class="mt-1.5 flex items-center gap-1.5 px-1 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </fieldset>
                                    <div>
                                        <label for="nikSiswa" class="block text-sm font-semibold text-slate-800">NIK siswa <span class="text-red-700">*</span></label>
                                        <input id="nikSiswa" type="text" inputmode="numeric" maxlength="16" wire:model="nikSiswa" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('nikSiswa')]) aria-invalid="{{ $errors->has('nikSiswa') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('nikSiswa') ? 'error-nikSiswa' : '' }}">
                                        @error('nikSiswa') <p id="error-nikSiswa" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        @if ($schoolLevel !== 'TK')
                                            <div class="mt-4">
                                                <label for="nisn" class="block text-sm font-semibold text-slate-800">NISN <span class="text-red-700">*</span></label>
                                                <input id="nisn" type="text" inputmode="numeric" maxlength="10" wire:model="nisn" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('nisn')]) aria-invalid="{{ $errors->has('nisn') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('nisn') ? 'error-nisn' : '' }}">
                                                @error('nisn') <p id="error-nisn" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <label for="nomorKk" class="block text-sm font-semibold text-slate-800">Nomor KK <span class="text-red-700">*</span></label>
                                        <input id="nomorKk" type="text" inputmode="numeric" maxlength="16" wire:model="nomorKk" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('nomorKk')]) aria-invalid="{{ $errors->has('nomorKk') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('nomorKk') ? 'error-nomorKk' : '' }}">
                                        @error('nomorKk') <p id="error-nomorKk" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="nomorAktaKelahiran" class="block text-sm font-semibold text-slate-800">No. Akta / No. Registrasi Akta <span class="text-red-700">*</span></label>
                                        <input id="nomorAktaKelahiran" type="text" maxlength="100" wire:model="nomorAktaKelahiran" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('nomorAktaKelahiran')]) aria-invalid="{{ $errors->has('nomorAktaKelahiran') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('nomorAktaKelahiran') ? 'error-nomorAktaKelahiran' : '' }}">
                                        @error('nomorAktaKelahiran') <p id="error-nomorAktaKelahiran" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="birthplace" class="block text-sm font-semibold text-slate-800">Tempat lahir <span class="text-red-700">*</span></label>
                                        <input id="birthplace" type="text" wire:model="birthplace" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('birthplace')]) aria-invalid="{{ $errors->has('birthplace') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('birthplace') ? 'error-birthplace' : '' }}">
                                        @error('birthplace') <p id="error-birthplace" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="birthdate" class="block text-sm font-semibold text-slate-800">Tanggal lahir <span class="text-red-700">*</span></label>
                                        <input id="birthdate" type="date" wire:model="birthdate" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('birthdate')]) aria-invalid="{{ $errors->has('birthdate') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('birthdate') ? 'error-birthdate' : '' }}">
                                        @error('birthdate') <p id="error-birthdate" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="asalSekolah" class="block text-sm font-semibold text-slate-800">Asal Sekolah <span class="text-red-700">*</span></label>
                                        <input id="asalSekolah" type="text" maxlength="255" placeholder="Contoh: TK ANNUR atau -" wire:model="asalSekolah" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('asalSekolah')]) aria-invalid="{{ $errors->has('asalSekolah') ? 'true' : 'false' }}" aria-describedby="asalSekolah-help{{ $errors->has('asalSekolah') ? ' error-asalSekolah' : '' }}">
                                        <p id="asalSekolah-help" class="mt-1 text-xs text-slate-600">Jika belum pernah sekolah, isi tanda (-).</p>
                                        @error('asalSekolah') <p id="error-asalSekolah" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="anakKe" class="block text-sm font-semibold text-slate-800">Anak ke- <span class="text-red-700">*</span></label>
                                        <input id="anakKe" type="number" min="1" wire:model="anakKe" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('anakKe')]) aria-invalid="{{ $errors->has('anakKe') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('anakKe') ? 'error-anakKe' : '' }}">
                                        @error('anakKe') <p id="error-anakKe" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="jumlahSaudara" class="block text-sm font-semibold text-slate-800">Jumlah saudara <span class="text-red-700">*</span></label>
                                        <input id="jumlahSaudara" type="number" min="0" wire:model="jumlahSaudara" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('jumlahSaudara')]) aria-invalid="{{ $errors->has('jumlahSaudara') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('jumlahSaudara') ? 'error-jumlahSaudara' : '' }}">
                                        <p class="mt-1 text-xs text-slate-600">Diisi terpisah dari daftar saudara kandung pada langkah berikutnya.</p>
                                        @error('jumlahSaudara') <p id="error-jumlahSaudara" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="hobi" class="block text-sm font-semibold text-slate-800">Hobi <span class="text-red-700">*</span></label>
                                        <input id="hobi" type="text" maxlength="255" wire:model="hobi" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('hobi')]) aria-invalid="{{ $errors->has('hobi') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('hobi') ? 'error-hobi' : '' }}">
                                        @error('hobi') <p id="error-hobi" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="citaCita" class="block text-sm font-semibold text-slate-800">Cita-cita <span class="text-red-700">*</span></label>
                                        <input id="citaCita" type="text" maxlength="255" wire:model="citaCita" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('citaCita')]) aria-invalid="{{ $errors->has('citaCita') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('citaCita') ? 'error-citaCita' : '' }}">
                                        @error('citaCita') <p id="error-citaCita" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="statusAnak" class="block text-sm font-semibold text-slate-800">Status anak</label>
                                        <input id="statusAnak" type="text" wire:model="statusAnak" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('statusAnak')]) aria-invalid="{{ $errors->has('statusAnak') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('statusAnak') ? 'error-statusAnak' : '' }}">
                                        @error('statusAnak') <p id="error-statusAnak" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="agama" class="block text-sm font-semibold text-slate-800">Agama</label>
                                        <input id="agama" type="text" wire:model="agama" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('agama')]) aria-invalid="{{ $errors->has('agama') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('agama') ? 'error-agama' : '' }}">
                                        @error('agama') <p id="error-agama" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="bahasaSehariHari" class="block text-sm font-semibold text-slate-800">Bahasa sehari-hari</label>
                                        <input id="bahasaSehariHari" type="text" wire:model="bahasaSehariHari" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('bahasaSehariHari')]) aria-invalid="{{ $errors->has('bahasaSehariHari') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('bahasaSehariHari') ? 'error-bahasaSehariHari' : '' }}">
                                        @error('bahasaSehariHari') <p id="error-bahasaSehariHari" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                                <h3 class="text-base font-semibold text-slate-950">Alamat Tinggal</h3>
                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label for="address" class="block text-sm font-semibold text-slate-800">Alamat lengkap <span class="text-red-700">*</span></label>
                                        <textarea id="address" wire:model="address" rows="3" @class(['mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('address')]) aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('address') ? 'error-address' : '' }}"></textarea>
                                        @error('address') <p id="error-address" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="rt" class="block text-sm font-semibold text-slate-800">RT</label>
                                        <input id="rt" type="text" inputmode="numeric" wire:model="rt" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('rt')]) aria-invalid="{{ $errors->has('rt') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('rt') ? 'error-rt' : '' }}">
                                        @error('rt') <p id="error-rt" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="rw" class="block text-sm font-semibold text-slate-800">RW</label>
                                        <input id="rw" type="text" inputmode="numeric" wire:model="rw" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('rw')]) aria-invalid="{{ $errors->has('rw') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('rw') ? 'error-rw' : '' }}">
                                        @error('rw') <p id="error-rw" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="kelurahan" class="block text-sm font-semibold text-slate-800">Kelurahan / Desa</label>
                                        <input id="kelurahan" type="text" wire:model="kelurahan" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('kelurahan')]) aria-invalid="{{ $errors->has('kelurahan') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('kelurahan') ? 'error-kelurahan' : '' }}">
                                        @error('kelurahan') <p id="error-kelurahan" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="kecamatan" class="block text-sm font-semibold text-slate-800">Kecamatan</label>
                                        <input id="kecamatan" type="text" wire:model="kecamatan" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('kecamatan')]) aria-invalid="{{ $errors->has('kecamatan') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('kecamatan') ? 'error-kecamatan' : '' }}">
                                        @error('kecamatan') <p id="error-kecamatan" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="kabupatenKota" class="block text-sm font-semibold text-slate-800">Kabupaten / Kota</label>
                                        <input id="kabupatenKota" type="text" wire:model="kabupatenKota" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('kabupatenKota')]) aria-invalid="{{ $errors->has('kabupatenKota') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('kabupatenKota') ? 'error-kabupatenKota' : '' }}">
                                        @error('kabupatenKota') <p id="error-kabupatenKota" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="provinsi" class="block text-sm font-semibold text-slate-800">Provinsi</label>
                                        <input id="provinsi" type="text" wire:model="provinsi" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('provinsi')]) aria-invalid="{{ $errors->has('provinsi') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('provinsi') ? 'error-provinsi' : '' }}">
                                        @error('provinsi') <p id="error-provinsi" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="kodePos" class="block text-sm font-semibold text-slate-800">Kode pos</label>
                                        <input id="kodePos" type="text" inputmode="numeric" maxlength="5" wire:model="kodePos" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('kodePos')]) aria-invalid="{{ $errors->has('kodePos') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('kodePos') ? 'error-kodePos' : '' }}">
                                        @error('kodePos') <p id="error-kodePos" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <fieldset @class(['rounded-xl p-1 -m-1 md:col-span-2', 'ring-1 ring-red-300' => $errors->has('jarakTempuhKategori')]) aria-invalid="{{ $errors->has('jarakTempuhKategori') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('jarakTempuhKategori') ? 'error-jarakTempuhKategori' : '' }}">
                                        <legend class="px-1 text-sm font-semibold text-slate-800">Jarak tempuh ke sekolah <span class="text-red-700">*</span></legend>
                                        <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            @foreach ($distanceOptions as $distanceValue => $distanceLabel)
                                                <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-800 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50 {{ $errors->has('jarakTempuhKategori') ? 'border-red-400' : '' }}">
                                                    <input type="radio" name="jarakTempuhKategori" wire:model.live="jarakTempuhKategori" value="{{ $distanceValue }}" class="h-5 w-5 border-slate-400 text-blue-700 focus:ring-blue-700"> {{ $distanceLabel }}
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('jarakTempuhKategori') <p id="error-jarakTempuhKategori" class="mt-1.5 flex items-center gap-1.5 px-1 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </fieldset>
                                    @if ($jarakTempuhKategori === 'more_than_1_km')
                                        <div>
                                            <label for="jarakTempuhDetail" class="block text-sm font-semibold text-slate-800">Detail jarak <span class="text-red-700">*</span></label>
                                            <div class="mt-2 flex rounded-xl shadow-sm">
                                                <input id="jarakTempuhDetail" type="text" inputmode="decimal" placeholder="Contoh: 2,5" wire:model="jarakTempuhDetail" @class(['h-12 min-w-0 flex-1 rounded-l-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('jarakTempuhDetail')]) aria-invalid="{{ $errors->has('jarakTempuhDetail') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('jarakTempuhDetail') ? 'error-jarakTempuhDetail' : '' }}">
                                                <span class="inline-flex items-center rounded-r-xl border border-l-0 border-slate-300 bg-slate-100 px-4 text-sm font-semibold text-slate-700">km</span>
                                            </div>
                                            @error('jarakTempuhDetail') <p id="error-jarakTempuhDetail" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                    <div>
                                        <label for="modaTransportasi" class="block text-sm font-semibold text-slate-800">Moda transportasi <span class="text-red-700">*</span></label>
                                        <select id="modaTransportasi" wire:model.live="modaTransportasi" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 bg-white px-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('modaTransportasi')]) aria-invalid="{{ $errors->has('modaTransportasi') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('modaTransportasi') ? 'error-modaTransportasi' : '' }}">
                                            <option value="">Pilih moda transportasi</option>
                                            @foreach ($transportationOptions as $transportationValue => $transportationLabel)
                                                <option value="{{ $transportationValue }}">{{ $transportationLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('modaTransportasi') <p id="error-modaTransportasi" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    @if ($modaTransportasi === 'lainnya')
                                        <div>
                                            <label for="modaTransportasiLainnya" class="block text-sm font-semibold text-slate-800">Moda transportasi lainnya <span class="text-red-700">*</span></label>
                                            <input id="modaTransportasiLainnya" type="text" maxlength="255" wire:model="modaTransportasiLainnya" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('modaTransportasiLainnya')]) aria-invalid="{{ $errors->has('modaTransportasiLainnya') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('modaTransportasiLainnya') ? 'error-modaTransportasiLainnya' : '' }}">
                                            @error('modaTransportasiLainnya') <p id="error-modaTransportasiLainnya" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                                <h3 class="text-base font-semibold text-slate-950">Data Fisik &amp; Kesehatan</h3>
                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="tinggiBadan" class="block text-sm font-semibold text-slate-800">Tinggi badan (cm) <span class="text-red-700">*</span></label>
                                        <input id="tinggiBadan" type="number" step="0.1" wire:model="tinggiBadan" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('tinggiBadan')]) aria-invalid="{{ $errors->has('tinggiBadan') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('tinggiBadan') ? 'error-tinggiBadan' : '' }}">
                                        @error('tinggiBadan') <p id="error-tinggiBadan" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="beratBadan" class="block text-sm font-semibold text-slate-800">Berat badan (kg) <span class="text-red-700">*</span></label>
                                        <input id="beratBadan" type="number" step="0.1" wire:model="beratBadan" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('beratBadan')]) aria-invalid="{{ $errors->has('beratBadan') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('beratBadan') ? 'error-beratBadan' : '' }}">
                                        @error('beratBadan') <p id="error-beratBadan" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="lingkarKepala" class="block text-sm font-semibold text-slate-800">Lingkar kepala (cm) <span class="text-red-700">*</span></label>
                                        <input id="lingkarKepala" type="number" step="0.1" wire:model="lingkarKepala" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('lingkarKepala')]) aria-invalid="{{ $errors->has('lingkarKepala') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('lingkarKepala') ? 'error-lingkarKepala' : '' }}">
                                        @error('lingkarKepala') <p id="error-lingkarKepala" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="golonganDarah" class="block text-sm font-semibold text-slate-800">Golongan darah <span class="text-red-700">*</span></label>
                                        <input id="golonganDarah" type="text" wire:model="golonganDarah" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('golonganDarah')]) aria-invalid="{{ $errors->has('golonganDarah') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('golonganDarah') ? 'error-golonganDarah' : '' }}">
                                        @error('golonganDarah') <p id="error-golonganDarah" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="riwayatPenyakit" class="block text-sm font-semibold text-slate-800">Riwayat penyakit <span class="text-red-700">*</span></label>
                                        <input id="riwayatPenyakit" type="text" wire:model="riwayatPenyakit" placeholder="Isi dengan 'Tidak Ada' jika tidak ada riwayat penyakit" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('riwayatPenyakit')]) aria-invalid="{{ $errors->has('riwayatPenyakit') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('riwayatPenyakit') ? 'error-riwayatPenyakit' : '' }}">
                                        @error('riwayatPenyakit') <p id="error-riwayatPenyakit" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </div>
                                    <fieldset @class(['rounded-xl p-1 -m-1 md:col-span-2', 'ring-1 ring-red-300' => $errors->has('berkebutuhanKhusus')]) aria-invalid="{{ $errors->has('berkebutuhanKhusus') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('berkebutuhanKhusus') ? 'error-berkebutuhanKhusus' : '' }}">
                                        <legend class="px-1 text-sm font-semibold text-slate-800">Berkebutuhan khusus <span class="text-red-700">*</span></legend>
                                        <div class="mt-2 grid grid-cols-2 gap-3">
                                            @foreach (['Tidak' => 'Tidak', 'Ya' => 'Ya'] as $bkValue => $bkLabel)
                                                <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-800 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50 {{ $errors->has('berkebutuhanKhusus') ? 'border-red-400' : '' }}">
                                                    <input type="radio" name="berkebutuhanKhusus" wire:model.live="berkebutuhanKhusus" value="{{ $bkValue }}" class="h-5 w-5 border-slate-400 text-blue-700 focus:ring-blue-700"> {{ $bkLabel }}
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('berkebutuhanKhusus') <p id="error-berkebutuhanKhusus" class="mt-1.5 flex items-center gap-1.5 px-1 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                    </fieldset>
                                    @if ($berkebutuhanKhusus === 'Ya')
                                        <div class="md:col-span-2">
                                            <label for="keteranganKebutuhanKhusus" class="block text-sm font-semibold text-slate-800">Keterangan kebutuhan khusus <span class="text-red-700">*</span></label>
                                            <textarea id="keteranganKebutuhanKhusus" wire:model="keteranganKebutuhanKhusus" rows="3" @class(['mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('keteranganKebutuhanKhusus')]) aria-invalid="{{ $errors->has('keteranganKebutuhanKhusus') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('keteranganKebutuhanKhusus') ? 'error-keteranganKebutuhanKhusus' : '' }}"></textarea>
                                            @error('keteranganKebutuhanKhusus') <p id="error-keteranganKebutuhanKhusus" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>
                @elseif ($currentStep === 3)
                    <section aria-labelledby="step-parent-title" data-step-section tabindex="-1" class="scroll-mt-4 outline-none sm:scroll-mt-8">
                        <h2 id="step-parent-title" class="text-xl font-semibold text-slate-950 sm:text-2xl">Data Orang Tua</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Lengkapi data ayah dan ibu. Data wali bersifat opsional dan hanya divalidasi jika diisi.</p>

                        <div class="mt-6 space-y-5">
                            @foreach (['father' => 'Data Ayah', 'mother' => 'Data Ibu', 'guardian' => 'Data Wali (Opsional)'] as $parentKey => $parentTitle)
                                <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                                    <legend class="px-2 text-base font-semibold text-slate-950">{{ $parentTitle }}</legend>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="{{ $parentKey }}-name" class="block text-sm font-semibold text-slate-800">Nama lengkap @if($parentKey !== 'guardian')<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-name" type="text" wire:model="{{ $parentKey }}.name" autocomplete="name" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.name')]) aria-invalid="{{ $errors->has($parentKey.'.name') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.name') ? 'error-'.$parentKey.'-name' : '' }}">
                                            @error($parentKey.'.name') <p id="error-{{ $parentKey }}-name" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        @if ($parentKey !== 'guardian')
                                            <fieldset @class(['rounded-xl p-1 -m-1', 'ring-1 ring-red-300' => $errors->has($parentKey.'.living_status')]) aria-invalid="{{ $errors->has($parentKey.'.living_status') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.living_status') ? 'error-'.$parentKey.'-living-status' : '' }}">
                                                <legend class="px-1 text-sm font-semibold text-slate-800">Status hidup <span class="text-red-700">*</span></legend>
                                                <div class="mt-2 grid grid-cols-2 gap-3">
                                                    @foreach (['hidup' => 'Hidup', 'wafat' => 'Wafat'] as $livingStatusValue => $livingStatusLabel)
                                                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-800 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50 {{ $errors->has($parentKey.'.living_status') ? 'border-red-400' : '' }}">
                                                            <input type="radio" name="{{ $parentKey }}[living_status]" wire:model="{{ $parentKey }}.living_status" value="{{ $livingStatusValue }}" class="h-5 w-5 border-slate-400 text-blue-700 focus:ring-blue-700"> {{ $livingStatusLabel }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @error($parentKey.'.living_status') <p id="error-{{ $parentKey }}-living-status" class="mt-1.5 flex items-center gap-1.5 px-1 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                            </fieldset>
                                        @endif
                                        <div>
                                            <label for="{{ $parentKey }}-nik" class="block text-sm font-semibold text-slate-800">NIK @if($parentKey !== 'guardian')<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-nik" type="text" inputmode="numeric" maxlength="16" wire:model="{{ $parentKey }}.nik" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.nik')]) aria-invalid="{{ $errors->has($parentKey.'.nik') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.nik') ? 'error-'.$parentKey.'-nik' : '' }}">
                                            @error($parentKey.'.nik') <p id="error-{{ $parentKey }}-nik" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-birthplace" class="block text-sm font-semibold text-slate-800">Tempat lahir @if(in_array($parentKey, ['father','mother'], true))<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-birthplace" type="text" wire:model="{{ $parentKey }}.birthplace" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.birthplace')]) aria-invalid="{{ $errors->has($parentKey.'.birthplace') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.birthplace') ? 'error-'.$parentKey.'-birthplace' : '' }}">
                                            @error($parentKey.'.birthplace') <p id="error-{{ $parentKey }}-birthplace" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-birthdate" class="block text-sm font-semibold text-slate-800">Tanggal lahir @if(in_array($parentKey, ['father','mother'], true))<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-birthdate" type="date" wire:model="{{ $parentKey }}.birthdate" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.birthdate')]) aria-invalid="{{ $errors->has($parentKey.'.birthdate') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.birthdate') ? 'error-'.$parentKey.'-birthdate' : '' }}">
                                            @error($parentKey.'.birthdate') <p id="error-{{ $parentKey }}-birthdate" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-education" class="block text-sm font-semibold text-slate-800">Pendidikan terakhir @if($parentKey !== 'guardian')<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-education" type="text" wire:model="{{ $parentKey }}.education_last" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.education_last')]) aria-invalid="{{ $errors->has($parentKey.'.education_last') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.education_last') ? 'error-'.$parentKey.'-education' : '' }}">
                                            @error($parentKey.'.education_last') <p id="error-{{ $parentKey }}-education" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-email" class="block text-sm font-semibold text-slate-800">Email</label>
                                            <input id="{{ $parentKey }}-email" type="email" wire:model="{{ $parentKey }}.email" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.email')]) aria-invalid="{{ $errors->has($parentKey.'.email') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.email') ? 'error-'.$parentKey.'-email' : '' }}">
                                            @error($parentKey.'.email') <p id="error-{{ $parentKey }}-email" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-phone" class="block text-sm font-semibold text-slate-800">Nomor telepon/WhatsApp @if($parentKey !== 'guardian')<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-phone" type="tel" inputmode="tel" wire:model="{{ $parentKey }}.phone" autocomplete="tel" placeholder="Contoh: 081234567890" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.phone')]) aria-invalid="{{ $errors->has($parentKey.'.phone') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.phone') ? 'error-'.$parentKey.'-phone' : '' }}">
                                            @error($parentKey.'.phone') <p id="error-{{ $parentKey }}-phone" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-occupation" class="block text-sm font-semibold text-slate-800">Pekerjaan @if($parentKey !== 'guardian')<span class="text-red-700">*</span>@endif</label>
                                            <input id="{{ $parentKey }}-occupation" type="text" wire:model="{{ $parentKey }}.occupation" placeholder="Contoh: Guru, Wiraswasta" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.occupation')]) aria-invalid="{{ $errors->has($parentKey.'.occupation') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.occupation') ? 'error-'.$parentKey.'-occupation' : '' }}">
                                            @error($parentKey.'.occupation') <p id="error-{{ $parentKey }}-occupation" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $parentKey }}-income" class="block text-sm font-semibold text-slate-800">Rentang penghasilan bulanan @if($parentKey !== 'guardian')<span class="text-red-700">*</span>@endif</label>
                                            <select id="{{ $parentKey }}-income" wire:model="{{ $parentKey }}.income_range" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 bg-white px-3 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($parentKey.'.income_range')]) aria-invalid="{{ $errors->has($parentKey.'.income_range') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has($parentKey.'.income_range') ? 'error-'.$parentKey.'-income' : '' }}">
                                                <option value="">Pilih rentang penghasilan</option>
                                                @foreach ($incomeOptions as $incomeValue => $incomeLabel)
                                                    <option value="{{ $incomeValue }}">{{ $incomeLabel }}</option>
                                                @endforeach
                                            </select>
                                            @error($parentKey.'.income_range') <p id="error-{{ $parentKey }}-income" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-950">Jika data ayah atau ibu tidak dapat diisi karena kondisi khusus, hubungi panitia PPDB sebelum melanjutkan. Jangan mengisi data fiktif.</p>
                    </section>
                @elseif ($currentStep === 4)
                    <section aria-labelledby="step-sibling-title" data-step-section tabindex="-1" class="scroll-mt-4 outline-none sm:scroll-mt-8">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 id="step-sibling-title" class="text-xl font-semibold text-slate-950 sm:text-2xl">Data Saudara Kandung</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600">Bagian ini opsional. Jika ditambahkan, seluruh data saudara wajib diisi.</p>
                            </div>
                            <button type="button" wire:click="addSibling" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-blue-700 px-4 text-sm font-semibold text-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 sm:w-auto">
                                <span class="material-symbols-outlined text-[20px]">add</span> Tambah Saudara
                            </button>
                        </div>

                        <div class="mt-6 space-y-4">
                            @forelse ($siblings as $siblingIndex => $sibling)
                                <fieldset wire:key="sibling-{{ $sibling['key'] }}" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                                    <legend class="px-2 text-sm font-semibold text-slate-950">Saudara {{ $loop->iteration }}</legend>
                                    <div class="flex justify-end">
                                        <button type="button" wire:click="removeSibling('{{ $sibling['key'] }}')" class="inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700" aria-label="Hapus saudara nomor {{ $loop->iteration }}">
                                            <span class="material-symbols-outlined text-[20px]">delete</span> Hapus
                                        </button>
                                    </div>
                                    <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="sibling-name-{{ $sibling['key'] }}" class="block text-sm font-semibold text-slate-800">Nama lengkap <span class="text-red-700">*</span></label>
                                            <input id="sibling-name-{{ $sibling['key'] }}" type="text" wire:model="siblings.{{ $siblingIndex }}.full_name" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('siblings.'.$siblingIndex.'.full_name')]) aria-invalid="{{ $errors->has('siblings.'.$siblingIndex.'.full_name') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('siblings.'.$siblingIndex.'.full_name') ? 'error-siblings-'.$siblingIndex.'-name' : '' }}">
                                            @error('siblings.'.$siblingIndex.'.full_name') <p id="error-siblings-{{ $siblingIndex }}-name" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="sibling-birthdate-{{ $sibling['key'] }}" class="block text-sm font-semibold text-slate-800">Tanggal lahir <span class="text-red-700">*</span></label>
                                            <input id="sibling-birthdate-{{ $sibling['key'] }}" type="date" wire:model="siblings.{{ $siblingIndex }}.birthdate" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('siblings.'.$siblingIndex.'.birthdate')]) aria-invalid="{{ $errors->has('siblings.'.$siblingIndex.'.birthdate') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('siblings.'.$siblingIndex.'.birthdate') ? 'error-siblings-'.$siblingIndex.'-birthdate' : '' }}">
                                            @error('siblings.'.$siblingIndex.'.birthdate') <p id="error-siblings-{{ $siblingIndex }}-birthdate" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                        <fieldset @class(['rounded-xl p-1 -m-1', 'ring-1 ring-red-300' => $errors->has('siblings.'.$siblingIndex.'.gender')]) aria-invalid="{{ $errors->has('siblings.'.$siblingIndex.'.gender') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('siblings.'.$siblingIndex.'.gender') ? 'error-siblings-'.$siblingIndex.'-gender' : '' }}">
                                            <legend class="px-1 text-sm font-semibold text-slate-800">Jenis kelamin <span class="text-red-700">*</span></legend>
                                            <div class="mt-2 grid grid-cols-2 gap-3">
                                                @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $genderValue => $genderLabel)
                                                    <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-medium text-slate-800 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50 {{ $errors->has('siblings.'.$siblingIndex.'.gender') ? 'border-red-400' : '' }}">
                                                        <input type="radio" name="siblings[{{ $siblingIndex }}][gender]" wire:model="siblings.{{ $siblingIndex }}.gender" value="{{ $genderValue }}" class="h-5 w-5 border-slate-400 text-blue-700 focus:ring-blue-700"> {{ $genderLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('siblings.'.$siblingIndex.'.gender') <p id="error-siblings-{{ $siblingIndex }}-gender" class="mt-1.5 flex items-center gap-1.5 px-1 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </fieldset>
                                        <div>
                                            <label for="sibling-education-{{ $sibling['key'] }}" class="block text-sm font-semibold text-slate-800">Pendidikan/Pekerjaan <span class="text-red-700">*</span></label>
                                            <input id="sibling-education-{{ $sibling['key'] }}" type="text" wire:model="siblings.{{ $siblingIndex }}.education_occupation" placeholder="Contoh: Kelas 5 SD, Mahasiswa, Karyawan" @class(['mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-base text-slate-950 focus:border-blue-700 focus:ring-blue-700', 'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has('siblings.'.$siblingIndex.'.education_occupation')]) aria-invalid="{{ $errors->has('siblings.'.$siblingIndex.'.education_occupation') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('siblings.'.$siblingIndex.'.education_occupation') ? 'error-siblings-'.$siblingIndex.'-edu' : '' }}">
                                            @error('siblings.'.$siblingIndex.'.education_occupation') <p id="error-siblings-{{ $siblingIndex }}-edu" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </fieldset>
                            @empty
                                <div class="rounded-2xl border-2 border-dashed border-slate-300 px-4 py-10 text-center text-sm leading-6 text-slate-600">Belum ada data saudara kandung. Anda dapat langsung melanjutkan.</div>
                            @endforelse
                        </div>
                    </section>
                @elseif ($currentStep === 5)
                    <section aria-labelledby="step-document-title" data-step-section tabindex="-1" class="scroll-mt-4 outline-none sm:scroll-mt-8">
                        <h2 id="step-document-title" class="text-xl font-semibold text-slate-950 sm:text-2xl">Dokumen Persyaratan</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Unggah seluruh dokumen. File disimpan secara privat dan hanya dapat diakses petugas berwenang.</p>

                        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($documentFields as $documentKey => $documentLabel)
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ $documentLabel }} <span class="text-red-700">*</span></p>
                                    <label for="document-{{ $documentKey }}" @class(['mt-2 flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-6 text-center transition focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2', 'border-emerald-400 bg-emerald-50' => isset($documents[$documentKey]) && !$errors->has('documents.'.$documentKey), 'border-red-500 bg-red-50' => $errors->has('documents.'.$documentKey), 'border-slate-300 bg-slate-50 hover:border-blue-500 hover:bg-blue-50' => !isset($documents[$documentKey]) && !$errors->has('documents.'.$documentKey)]) aria-invalid="{{ $errors->has('documents.'.$documentKey) ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('documents.'.$documentKey) ? 'error-documents-'.$documentKey : '' }}">
                                        <input id="document-{{ $documentKey }}" type="file" wire:model="documents.{{ $documentKey }}" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="sr-only">
                                        <span class="material-symbols-outlined text-4xl {{ $errors->has('documents.'.$documentKey) ? 'text-red-700' : (isset($documents[$documentKey]) ? 'text-emerald-700' : 'text-blue-800') }}">{{ $errors->has('documents.'.$documentKey) ? 'error' : (isset($documents[$documentKey]) ? 'check_circle' : 'cloud_upload') }}</span>
                                        @if (isset($documents[$documentKey]) && is_object($documents[$documentKey]) && method_exists($documents[$documentKey], 'getClientOriginalName'))
                                            <span class="mt-2 max-w-full break-all text-sm font-semibold {{ $errors->has('documents.'.$documentKey) ? 'text-red-900' : 'text-emerald-900' }}">{{ $documents[$documentKey]->getClientOriginalName() }}</span>
                                            <span class="mt-3 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm">Ganti File</span>
                                        @else
                                            <span class="mt-2 text-sm font-semibold text-slate-950">Pilih {{ $documentLabel }}</span>
                                            <span class="mt-1 text-xs leading-5 text-slate-600">PDF, JPG, JPEG, atau PNG · Maksimal 2 MB</span>
                                            <span class="mt-3 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Pilih File</span>
                                        @endif
                                        <span wire:loading wire:target="documents.{{ $documentKey }}" class="mt-3 text-sm font-semibold text-blue-800">Mengunggah file...</span>
                                    </label>
                                    @error('documents.'.$documentKey) <p id="error-documents-{{ $documentKey }}" class="mt-2 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    </section>
                @else
                    <section aria-labelledby="step-review-title" data-step-section tabindex="-1" class="scroll-mt-4 outline-none sm:scroll-mt-8">
                        <h2 id="step-review-title" class="text-xl font-semibold text-slate-950 sm:text-2xl">Konfirmasi Data</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Periksa kembali data sebelum mengirim pendaftaran.</p>

                        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 sm:p-5">
                                <h3 class="font-semibold text-slate-950">Tujuan dan Calon Siswa</h3>
                                <dl class="mt-4 space-y-3 text-sm">
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Tahun ajaran</dt><dd class="break-words text-right font-medium text-slate-950">{{ $selectedYearLabel }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Jenjang / tingkat</dt><dd class="break-words text-right font-medium text-slate-950">{{ $schoolLevel ?: '-' }} / {{ $selectedGradeLabel }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Kategori pendaftar</dt><dd class="break-words text-right font-medium text-slate-950">{{ $selectedApplicantCategoryLabel }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Nama lengkap</dt><dd class="break-words text-right font-medium text-slate-950">{{ $fullName ?: '-' }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Jenis kelamin</dt><dd class="text-right font-medium text-slate-950">{{ $gender === 'L' ? 'Laki-laki' : ($gender === 'P' ? 'Perempuan' : '-') }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">NISN</dt><dd class="text-right font-medium text-slate-950">{{ $schoolLevel === 'TK' ? 'Tidak diperlukan' : ($nisn ?: '-') }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">No. Akta</dt><dd class="break-words text-right font-medium text-slate-950">{{ $nomorAktaKelahiran ?: '-' }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Anak ke- / jumlah saudara</dt><dd class="text-right font-medium text-slate-950">{{ $anakKe ?: '-' }} / {{ $jumlahSaudara === '' ? '-' : $jumlahSaudara }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Hobi / cita-cita</dt><dd class="break-words text-right font-medium text-slate-950">{{ $hobi ?: '-' }} / {{ $citaCita ?: '-' }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Jarak tempuh</dt><dd class="text-right font-medium text-slate-950">{{ $selectedDistanceLabel }}{{ $jarakTempuhKategori === 'more_than_1_km' && $jarakTempuhDetail !== '' ? ' ('.$jarakTempuhDetail.' km)' : '' }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Moda transportasi</dt><dd class="break-words text-right font-medium text-slate-950">{{ $modaTransportasi === 'lainnya' ? ($modaTransportasiLainnya ?: '-') : $selectedTransportationLabel }}</dd></div>
                                </dl>
                            </div>
                            <div class="rounded-2xl border border-slate-200 p-4 sm:p-5">
                                <h3 class="font-semibold text-slate-950">Orang Tua dan Saudara</h3>
                                <dl class="mt-4 space-y-3 text-sm">
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Ayah</dt><dd class="break-words text-right font-medium text-slate-950">{{ $father['name'] ?: '-' }} ({{ ($father['living_status'] ?? '') === 'hidup' ? 'Hidup' : (($father['living_status'] ?? '') === 'wafat' ? 'Wafat' : '-') }})</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Ibu</dt><dd class="break-words text-right font-medium text-slate-950">{{ $mother['name'] ?: '-' }} ({{ ($mother['living_status'] ?? '') === 'hidup' ? 'Hidup' : (($mother['living_status'] ?? '') === 'wafat' ? 'Wafat' : '-') }})</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Wali</dt><dd class="break-words text-right font-medium text-slate-950">{{ $guardian['name'] ?: 'Tidak ada' }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Data saudara rinci</dt><dd class="text-right font-medium text-slate-950">{{ count($siblings) }}</dd></div>
                                </dl>
                            </div>
                        </div>

                        <label @class(['mt-6 flex min-h-14 cursor-pointer items-start gap-3 rounded-xl border p-4 focus-within:ring-2 focus-within:ring-blue-700 has-[:checked]:border-blue-700 has-[:checked]:bg-blue-50', 'border-red-400 bg-red-50' => $errors->has('consent'), 'border-slate-300 bg-slate-50' => !$errors->has('consent')])>
                            <input type="checkbox" wire:model="consent" class="mt-0.5 h-5 w-5 rounded border-slate-400 text-blue-700 focus:ring-blue-700" aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('consent') ? 'error-consent' : '' }}">
                            <span class="text-sm leading-6 text-slate-800">Saya menyetujui pemrosesan data dan dokumen untuk keperluan pendaftaran PPDB.</span>
                        </label>
                        @error('consent') <p id="error-consent" class="mt-2 flex items-center gap-1.5 text-sm text-red-700" role="alert" data-error-field><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror

                        <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-primary px-5 text-base font-semibold text-white hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Kirim Pendaftaran</span>
                            <span wire:loading wire:target="submit">Mengirim pendaftaran...</span>
                        </button>
                        @error('submission') <p class="mt-2 flex items-center gap-1.5 text-sm text-red-700" role="alert"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                    </section>
                @endif

                <div class="mt-8 grid grid-cols-2 gap-3 border-t border-slate-200 pt-5">
                    <button type="button" wire:click="previousStep" wire:loading.attr="disabled" wire:target="previousStep" @disabled($currentStep === 1) class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <span class="material-symbols-outlined text-[20px]">arrow_back</span> Kembali
                    </button>

                    @if ($currentStep < 6)
                        <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-3 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                            <span wire:loading.remove wire:target="nextStep">Selanjutnya</span>
                            <span wire:loading wire:target="nextStep">Memeriksa...</span>
                            <span class="material-symbols-outlined text-[20px]" wire:loading.remove wire:target="nextStep">arrow_forward</span>
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
