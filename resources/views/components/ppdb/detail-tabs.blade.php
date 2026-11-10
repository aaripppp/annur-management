@props(['prefix', 'showFinance' => true])

<div class="mb-6 overflow-x-auto border-b border-outline-variant" role="tablist" aria-label="Bagian detail">
    <div class="flex min-w-max gap-1 px-1">
        <button
            type="button"
            x-ref="biodataTab"
            @click="selectDetailTab('biodata')"
            @keydown.arrow-right.prevent="moveDetailTab(1)"
            @keydown.arrow-left.prevent="moveDetailTab(-1)"
            @keydown.home.prevent="selectDetailTab('biodata'); $nextTick(() => $refs.biodataTab.focus())"
            @keydown.end.prevent="selectDetailTab(@js($showFinance ? 'finance' : 'documents'))"
            role="tab"
            id="{{ $prefix }}-tab-biodata"
            aria-controls="{{ $prefix }}-panel-biodata"
            :aria-selected="activeDetailTab === 'biodata'"
            :tabindex="activeDetailTab === 'biodata' ? 0 : -1"
            :class="activeDetailTab === 'biodata' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
            class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-label-lg font-label-lg transition-colors"
            data-detail-tab="biodata"
        >
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">person</span>
            Biodata
        </button>
        <button
            type="button"
            x-ref="documentsTab"
            @click="selectDetailTab('documents')"
            @keydown.arrow-right.prevent="moveDetailTab(1)"
            @keydown.arrow-left.prevent="moveDetailTab(-1)"
            @keydown.home.prevent="selectDetailTab('biodata'); $nextTick(() => $refs.biodataTab.focus())"
            @keydown.end.prevent="selectDetailTab(@js($showFinance ? 'finance' : 'documents'))"
            role="tab"
            id="{{ $prefix }}-tab-documents"
            aria-controls="{{ $prefix }}-panel-documents"
            :aria-selected="activeDetailTab === 'documents'"
            :tabindex="activeDetailTab === 'documents' ? 0 : -1"
            :class="activeDetailTab === 'documents' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
            class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-label-lg font-label-lg transition-colors"
            data-detail-tab="documents"
        >
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">folder</span>
            Dokumen
        </button>
        @if($showFinance)
        <button
            type="button"
            x-ref="financeTab"
            @click="selectDetailTab('finance')"
            @keydown.arrow-right.prevent="moveDetailTab(1)"
            @keydown.arrow-left.prevent="moveDetailTab(-1)"
            @keydown.home.prevent="selectDetailTab('biodata'); $nextTick(() => $refs.biodataTab.focus())"
            @keydown.end.prevent="selectDetailTab('finance'); $nextTick(() => $refs.financeTab.focus())"
            role="tab"
            id="{{ $prefix }}-tab-finance"
            aria-controls="{{ $prefix }}-panel-finance"
            :aria-selected="activeDetailTab === 'finance'"
            :tabindex="activeDetailTab === 'finance' ? 0 : -1"
            :class="activeDetailTab === 'finance' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
            class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-label-lg font-label-lg transition-colors"
            data-detail-tab="finance"
        >
            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">payments</span>
            Tagihan &amp; Pembayaran
        </button>
        @endif
    </div>
</div>
