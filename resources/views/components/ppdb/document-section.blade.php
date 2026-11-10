@props(['applicant', 'student' => null])

@php
    $applicant->loadMissing('ppdbDocuments');
    $applicant->ppdbDocuments->each(fn ($document) => $document->setRelation('prospectiveStudent', $applicant));
    $documentsByType = $applicant->ppdbDocuments->keyBy(fn ($document) => $document->document_type->value);
    $prospectiveStudentId = $applicant->id;
    $studentId = $student?->id;
@endphp

<section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest" data-ppdb-document-section x-data="ppdbDocumentPreview()" @keydown.escape.window="closePreview()">
    <div class="border-b border-outline-variant p-5">
        <h2 class="text-headline-sm font-headline-sm text-on-surface">Dokumen PPDB</h2>
        <p class="mt-1 text-body-sm text-on-surface-variant">Dokumen tersimpan secara privat dan hanya dapat diakses oleh petugas yang berwenang.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
        @foreach(App\Enums\PpdbDocumentType::cases() as $documentType)
            @php
                $document = $documentsByType->get($documentType->value);
                $fileType = $document ? match ($document->mime_type) {
                    'application/pdf' => 'PDF',
                    'image/jpeg' => 'JPG',
                    'image/png' => 'PNG',
                    default => 'Berkas',
                } : null;
            @endphp

            <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-low/30 p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $document ? 'bg-primary-fixed text-primary' : 'bg-surface-container-high text-on-surface-variant' }}" aria-hidden="true">
                        <span class="material-symbols-outlined text-[21px]">{{ $document?->mime_type === 'application/pdf' ? 'picture_as_pdf' : 'description' }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-body-md font-semibold text-on-surface">{{ $documentType->label() }}</h3>
                        @if($document)
                            <p class="mt-1 break-all text-body-sm text-on-surface-variant">{{ $document->original_name }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-secondary-fixed px-2 py-0.5 text-label-sm font-label-sm text-on-secondary-fixed">Diunggah</span>
                                <span class="text-label-sm text-on-surface-variant">{{ $fileType }}</span>
                            </div>
                        @else
                            <p class="mt-1 text-body-sm text-on-surface-variant">Belum diunggah</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-outline-variant/60 pt-3 sm:flex-row sm:flex-wrap">
                    @if($document && (auth()->user()?->can('view', $document) || auth()->user()?->can('download', $document)))
                        @can('view', $document)
                            <button
                                type="button"
                                @click="openPreview($event, @js(route('ppdb.documents.preview', $document)), @js(route('ppdb.documents.download', $document)), @js($documentType->label()), @js($document->mime_type))"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-primary/40 px-3 py-2 font-label-md text-primary transition-colors hover:bg-primary-fixed/50"
                                aria-haspopup="dialog"
                                data-document-preview
                            >
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                Pratinjau
                            </button>
                        @endcan
                        @can('download', $document)
                            <a href="{{ route('ppdb.documents.download', $document) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary transition-colors hover:bg-primary/90">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                                Unduh
                            </a>
                        @endcan
                    @endif
                    @can('replaceDocument', $applicant)
                        <button
                            type="button"
                            wire:click="$dispatch('ppdb-document:replace', { prospectiveStudentId: {{ $prospectiveStudentId }}, studentId: {{ $studentId ?? 'null' }}, documentId: {{ $document?->id ?? 'null' }}, documentType: @js($documentType->value) })"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface-variant transition-colors hover:bg-surface-container"
                            data-document-replace
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">swap_horiz</span>
                            {{ $document ? 'Ganti Dokumen' : 'Upload Dokumen' }}
                        </button>
                    @endcan
                </div>
            </article>
        @endforeach
    </div>

    <div
        x-show="previewOpen"
        x-cloak
        style="display: none"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-on-surface/50 p-0 backdrop-blur-sm sm:p-4"
        role="presentation"
        @click.self="closePreview()"
        data-document-preview-modal
    >
        <div
            x-ref="previewDialog"
            @keydown.tab="trapPreviewFocus($event)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ppdb-preview-title"
            aria-describedby="ppdb-preview-help"
            class="flex h-full max-h-screen w-full flex-col overflow-hidden bg-surface-container-lowest shadow-2xl sm:h-[90vh] sm:max-w-7xl sm:rounded-2xl sm:border sm:border-outline-variant"
        >
            <header class="flex shrink-0 items-center justify-between gap-4 border-b border-outline-variant px-4 py-3 sm:px-5">
                <div class="min-w-0">
                    <p class="text-label-sm font-label-sm text-on-surface-variant">Pratinjau Dokumen</p>
                    <h2 id="ppdb-preview-title" class="truncate text-title-lg font-semibold text-on-surface" x-text="previewLabel"></h2>
                </div>
                <button x-ref="previewClose" type="button" @click="closePreview()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-surface-container hover:text-error" aria-label="Tutup pratinjau dokumen">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </header>

            <div class="relative min-h-0 flex-1 overflow-auto bg-surface-container-low p-2 sm:p-4">
                <div x-show="previewLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-surface-container-low" aria-live="polite">
                    <div class="flex items-center gap-3 rounded-xl bg-surface-container-lowest px-4 py-3 text-body-md text-on-surface shadow-sm">
                        <span class="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                        Memuat dokumen...
                    </div>
                </div>

                <template x-if="previewOpen && previewMime === 'application/pdf'">
                    <iframe :src="previewUrl" @load="previewLoaded()" @@error="previewFailed()" class="h-full min-h-[70vh] w-full rounded-lg bg-white" title="Pratinjau PDF" tabindex="0"></iframe>
                </template>

                <template x-if="previewOpen && ['image/jpeg', 'image/png'].includes(previewMime)">
                    <div class="flex min-h-full items-center justify-center">
                        <img :src="previewUrl" :alt="`Pratinjau ${previewLabel}`" @load="previewLoaded()" @@error="previewFailed()" class="max-h-full max-w-full rounded-lg object-contain shadow-sm">
                    </div>
                </template>

                <div x-show="previewError" class="flex min-h-full items-center justify-center p-6 text-center" role="alert">
                    <div>
                        <span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">broken_image</span>
                        <p class="mt-2 font-semibold text-on-surface">Pratinjau tidak dapat ditampilkan.</p>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Gunakan tombol unduh untuk membuka dokumen melalui aplikasi yang sesuai.</p>
                    </div>
                </div>
            </div>

            <footer class="flex shrink-0 flex-col-reverse gap-2 border-t border-outline-variant bg-surface px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p id="ppdb-preview-help" class="text-body-sm text-on-surface-variant">Jika penampil PDF tidak tersedia di browser, unduh dokumen untuk membukanya.</p>
                <div class="flex gap-2">
                    <button type="button" @click="closePreview()" class="flex-1 rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container sm:flex-none">Tutup</button>
                    <a :href="downloadUrl" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:bg-primary/90 sm:flex-none">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">download</span>
                        Unduh
                    </a>
                </div>
            </footer>
        </div>
    </div>
</section>
