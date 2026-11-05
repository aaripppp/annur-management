<div class="mx-auto max-w-2xl py-8 sm:py-14">
    <section class="overflow-hidden rounded-3xl bg-surface shadow-xl ring-1 ring-outline-variant">
        <div class="bg-primary-container px-6 py-8 text-center sm:px-10">
            <span class="material-symbols-outlined text-6xl text-primary">check_circle</span>
            <h1 class="mt-3 text-headline-md font-headline-md text-on-primary-container">Pendaftaran Berhasil</h1>
            <p class="mt-2 text-body-lg text-on-primary-container">Data dan dokumen pendaftaran telah diterima.</p>
        </div>

        <div class="px-6 py-8 sm:px-10">
            <p class="text-body-sm text-on-surface-variant">Nomor pendaftaran</p>
            <p class="mt-1 break-all text-headline-sm font-headline-sm text-on-surface">{{ $registrationNumber }}</p>

            <dl class="mt-6 divide-y divide-outline-variant rounded-xl border border-outline-variant px-4">
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-body-md text-on-surface-variant">Tahun ajaran</dt>
                    <dd class="text-right text-body-md font-medium text-on-surface">{{ $academicYear }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-4">
                    <dt class="text-body-md text-on-surface-variant">Kelas tujuan</dt>
                    <dd class="text-right text-body-md font-medium text-on-surface">{{ $className }}</dd>
                </div>
            </dl>

            <div class="mt-6 rounded-xl bg-surface-container-low p-4 text-body-md text-on-surface-variant">
                Simpan nomor pendaftaran ini. Panitia PPDB akan menghubungi orang tua atau wali melalui nomor telepon yang didaftarkan.
            </div>
        </div>
    </section>
</div>
