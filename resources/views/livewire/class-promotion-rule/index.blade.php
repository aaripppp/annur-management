<div>
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Aturan Kenaikan Kelas</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Atur tujuan kelas saat proses kenaikan tahun ajaran.</p>
        </div>
        <div class="flex flex-col sm:flex-row items-end sm:items-center gap-3">
            <p class="text-body-md text-on-surface-variant">
                <span class="font-label-lg text-on-surface">{{ $totalRules }}</span> aturan terdaftar
            </p>
            <button
                type="button"
                wire:click="openHistory"
                class="inline-flex items-center gap-2 px-5 py-2.5 border border-outline-variant hover:bg-surface-container text-on-surface-variant rounded-xl font-label-lg transition-colors"
            >
                <span class="material-symbols-outlined text-[18px]">history</span>
                Riwayat Perubahan
            </button>
        </div>
    </div>

    <!-- Rule Coverage Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-stack-lg">
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-center">
            <p class="text-headline-sm font-headline-sm text-on-surface">{{ $coverage['total_classes'] }}</p>
            <p class="text-body-sm text-on-surface-variant mt-1">Total Kelas</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-center">
            <p class="text-headline-sm font-headline-sm text-primary">{{ $coverage['active_rule_count'] }}</p>
            <p class="text-body-sm text-on-surface-variant mt-1">Rule Aktif</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-center">
            <p class="text-headline-sm font-headline-sm text-on-surface">{{ $coverage['inactive_rule_count'] }}</p>
            <p class="text-body-sm text-on-surface-variant mt-1">Rule Tidak Aktif</p>
        </div>
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-center">
            <p class="text-headline-sm font-headline-sm {{ $coverage['missing_rule_count'] > 0 ? 'text-error' : 'text-on-surface' }}">{{ $coverage['missing_rule_count'] }}</p>
            <p class="text-body-sm text-on-surface-variant mt-1">Belum Punya Rule</p>
        </div>
    </div>

    @if ($coverage['missing_rule_count'] > 0)
        <div class="mb-stack-lg flex items-start gap-3 bg-error-container border border-error/40 text-on-error-container rounded-xl px-4 py-3">
            <span class="material-symbols-outlined text-error mt-0.5">warning</span>
            <p class="text-body-md font-body-md text-on-error-container">
                Promotion tidak dapat dijalankan sampai seluruh kelas sumber memiliki aturan aktif.
            </p>
        </div>
    @endif

    <!-- Toast Success -->
    @if (session()->has('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 3000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Toast Error -->
    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Jenjang Tabs -->
    <div class="border-b border-outline-variant mb-stack-lg">
        <nav class="flex items-center gap-1 overflow-x-auto" aria-label="Jenjang">
            @foreach ($jenjangs as $jenjang)
                <button
                    type="button"
                    wire:click="setJenjang('{{ $jenjang->value }}')"
                    class="relative px-4 py-2.5 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $filterJenjang === $jenjang->value ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    {{ $jenjang->value }}
                    @if ($filterJenjang === $jenjang->value)
                        <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Table -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant flex flex-col sm:flex-row justify-between items-center gap-4">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Kelas</h2>
            <p class="text-body-sm text-on-surface-variant text-right">
                Atur = buat aturan baru. Hapus = hapus aturan selamanya.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-3 px-3 w-14 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Kelas Asal</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Status Rule</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Aksi</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Kelas Tujuan</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($rows as $row)
                        @php
                            $class = $row['class'];
                            $decision = $row['decision'];
                            $rule = $row['rule'];
                            $isBlocked = $decision['action'] === 'blocked';
                            $ruleStatus = $row['rule_status'];
                        @endphp
                        <tr wire:key="promotion-rule-{{ $class->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ $loop->iteration }}</td>
                            <td class="py-4 px-6">
                                <p class="text-body-md font-body-md text-on-surface font-semibold">{{ $class->name }}</p>
                                <p class="text-body-sm text-on-surface-variant">{{ $class->level_name }}</p>
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if ($ruleStatus === 'missing')
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface-variant">
                                        Belum Dikonfigurasi
                                    </span>
                                @elseif ($ruleStatus === 'active')
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if ($isBlocked)
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container">
                                        Tidak Dapat Diproses
                                    </span>
                                @elseif ($decision['action'] === 'graduate')
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface-variant">
                                        Lulus
                                    </span>
                                @else
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        Naik Kelas
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-body-md text-on-surface">
                                {{ $isBlocked ? ($decision['label'] ?? '—') : ($decision['target']?->name ?? 'Lulus') }}
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if ($isBlocked)
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container">
                                        Diblokir
                                    </span>
                                @else
                                    <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-secondary-container text-on-secondary-container">
                                        Dapat Diproses
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-right">
                                @if ($rule)
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="edit({{ $class->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </button>
                                        <button wire:click="confirmDelete({{ $rule->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus Aturan">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </div>
                                @else
                                    <button wire:click="openCreate({{ $class->id }})" class="inline-flex items-center gap-1 px-4 py-2 rounded-lg text-label-lg font-label-lg bg-primary/5 text-primary hover:bg-primary/10 transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">tune</span>
                                        Atur
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">rule</span>
                                <p>Tidak ada kelas ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">{{ $ruleId ? 'Atur Ulang Aturan' : 'Atur Kenaikan Kelas' }}</h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <div class="flex flex-col gap-5">
                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-1">Kelas Asal</label>
                            <input type="text" readonly value="{{ $sourceClassName }}" class="w-full border-outline-variant bg-surface-container-low text-on-surface-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                        </div>
                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-2">Aksi Efektif <span class="text-error">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center gap-2 border border-outline-variant rounded-xl px-4 py-3 cursor-pointer transition-colors {{ $action === 'promote' ? 'border-primary bg-primary/5' : 'hover:bg-surface-container' }}">
                                    <input type="radio" value="promote" wire:model="action" class="accent-primary">
                                    <span class="text-body-md font-body-md text-on-surface">Naik Kelas</span>
                                </label>
                                <label class="flex items-center gap-2 border border-outline-variant rounded-xl px-4 py-3 cursor-pointer transition-colors {{ $action === 'graduate' ? 'border-primary bg-primary/5' : 'hover:bg-surface-container' }}">
                                    <input type="radio" value="graduate" wire:model="action" class="accent-primary">
                                    <span class="text-body-md font-body-md text-on-surface">Lulus</span>
                                </label>
                            </div>
                            @error('action') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        @if($action === 'promote')
                            <div>
                                <label for="targetClassId" class="block text-label-md font-label-md text-on-surface mb-1">Kelas Tujuan <span class="text-error">*</span></label>
                                <select id="targetClassId" wire:model="targetClassId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Pilih Kelas Tujuan --</option>
                                    @foreach ($targetClassOptions as $targetOption)
                                        @if ($targetOption->id !== $sourceClassId)
                                            <option value="{{ $targetOption->id }}">{{ $targetOption->name }} ({{ $targetOption->level_name }})</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('targetClassId') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-label-md font-label-md text-on-surface">Status Aturan</span>
                            <div class="flex items-center gap-3">
                                <span class="text-body-md text-on-surface-variant">{{ $isActive ? 'Aktif' : 'Nonaktif' }}</span>
                                <button type="button" wire:click="$toggle('isActive')" class="relative w-11 h-6 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 {{ $isActive ? 'bg-primary' : 'bg-outline-variant' }}">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform {{ $isActive ? 'translate-x-5' : '' }}"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="save" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($isDeleteModalOpen && $deletingRuleId)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Aturan Kelas?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Aturan kelas <strong>{{ $deleteSourceClassName }}</strong> akan dihapus selamanya.
                        Kenaikan kelas kelas ini akan diblokir sampai ada aturan baru yang dikonfigurasi.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDelete" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="deleteRule" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">Hapus</button>
                </div>
            </div>
        </div>
    @endif

    <!-- History Modal -->
    @if($showHistoryModal)
        <div class="fixed inset-0 z-50 flex items-start justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[85vh] mt-6">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface sticky top-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Perubahan Aturan</h3>
                            <p class="text-body-sm text-on-surface-variant mt-0.5">Jejak audit perubahan aturan kenaikan kelas (50 entri terakhir).</p>
                        </div>
                        <button wire:click="closeHistoryModal" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Tutup">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between pt-4">
                        <p class="text-body-sm text-on-surface-variant">
                            <span class="font-label-lg text-on-surface">{{ $history->count() }}</span> entri
                        </p>
                        <button
                            type="button"
                            wire:click="openDeleteAllHistory"
                            @if($history->isEmpty()) disabled @endif
                            class="inline-flex items-center gap-2 px-4 py-2 text-label-lg font-label-lg rounded-lg border border-error/40 text-error hover:bg-error/10 transition-colors {{ $history->isEmpty() ? 'opacity-40 cursor-not-allowed' : '' }}"
                        >
                            <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
                            Hapus Semua Riwayat
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    @forelse ($history as $entry)
                        @php
                            $actionStyle = match ($entry['action_type']) {
                                'created_manual' => 'bg-primary-fixed text-on-primary-fixed',
                                'edited_manual' => 'bg-surface-container-high text-on-surface-variant',
                                'rule_deleted' => 'bg-error-container text-on-error-container',
                                'rule_activated' => 'bg-primary-fixed text-on-primary-fixed',
                                'rule_deactivated' => 'bg-error-container text-on-error-container',
                                default => 'bg-surface-container-high text-on-surface-variant',
                            };
                            $actionLabel = match ($entry['action_type']) {
                                'created_manual' => 'Dibuat',
                                'edited_manual' => 'Diubah',
                                'rule_deleted' => 'Dihapus',
                                'rule_activated' => 'Diaktifkan',
                                'rule_deactivated' => 'Dinonaktifkan',
                                default => $entry['action_type'],
                            };
                        @endphp
                        <div wire:key="history-{{ $entry['id'] }}" class="flex items-start gap-3 py-3 px-2 border-b border-outline-variant/60 last:border-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-label-sm font-label-sm {{ $actionStyle }}">{{ $actionLabel }}</span>
                                    <p class="text-body-md font-body-md text-on-surface">{{ $entry['source_class_name'] }}</p>
                                </div>
                                <p class="text-body-sm text-on-surface-variant mt-0.5">
                                    {{ $entry['changed_by_name'] }} • {{ $entry['created_at'] ? $entry['created_at']->format('d/m/Y H:i') : '—' }}
                                </p>
                                @if ($entry['action_type'] === 'rule_deleted')
                                    <p class="text-body-sm text-on-surface mt-1">
                                        <span class="text-on-surface-variant">Sebelum:</span> {{ $entry['old_label'] }} → dihapus
                                    </p>
                                @elseif ($entry['action_type'] === 'rule_activated')
                                    <p class="text-body-sm text-on-surface mt-1">
                                        <span class="text-on-surface-variant">Status menjadi:</span> Aktif — {{ $entry['new_label'] }}
                                    </p>
                                @elseif ($entry['action_type'] === 'rule_deactivated')
                                    <p class="text-body-sm text-on-surface mt-1">
                                        <span class="text-on-surface-variant">Status menjadi:</span> Nonaktif — {{ $entry['new_label'] }}
                                    </p>
                                @else
                                    @if ($entry['old_label'] !== '—')
                                        <p class="text-body-sm text-on-surface mt-1"><span class="text-on-surface-variant">Sebelum:</span> {{ $entry['old_label'] }}</p>
                                    @endif
                                    <p class="text-body-sm text-on-surface">
                                        <span class="text-on-surface-variant">Setelah:</span> {{ $entry['new_label'] }}
                                    </p>
                                @endif
                            </div>
                            <button
                                type="button"
                                wire:click="confirmDeleteHistory({{ $entry['id'] }})"
                                class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors shrink-0"
                                title="Hapus riwayat ini"
                            >
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    @empty
                        <div class="py-14 text-center">
                            <span class="material-symbols-outlined text-4xl block mb-2 text-on-surface-variant">history</span>
                            <p class="text-body-md text-on-surface-variant">Belum ada riwayat perubahan.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- Delete History Confirmation Modal -->
    @if($showDeleteHistoryModal && $historyToDeleteId)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus riwayat perubahan ini?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Riwayat yang dihapus tidak dapat dikembalikan.
                        Aturan kenaikan kelas tidak akan ikut terhapus.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDeleteHistory" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="deleteHistory" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">Hapus Riwayat</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete All History Confirmation Modal -->
    @if($showDeleteAllHistoryModal)
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete_sweep</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus semua riwayat perubahan?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Semua log perubahan aturan kenaikan kelas akan dihapus.
                        Aturan kenaikan kelas tetap aman dan tidak akan berubah.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDeleteAllHistory" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="deleteAllHistory" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">Hapus Semua Riwayat</button>
                </div>
            </div>
        </div>
    @endif
</div>