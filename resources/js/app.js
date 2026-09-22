
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const sidebarStorageKeys = {
    siswa: 'sidebar.siswa.open',
    daycare: 'sidebar.daycare.open',
    masterData: 'sidebar.masterData.open',
};

function clearSidebarState() {
    if (typeof window === 'undefined') {
        return;
    }

    Object.values(sidebarStorageKeys).forEach((storageKey) => {
        try {
            window.localStorage.removeItem(storageKey);
        } catch (error) {
            // localStorage unavailable - nothing to clear.
        }
    });

    try {
        window.sessionStorage.removeItem('sidebar.scrollTop');
    } catch (error) {
        // sessionStorage unavailable - nothing to clear.
    }
}

if (typeof document !== 'undefined' && document.querySelector('meta[name="sidebar-reset"]')) {
    clearSidebarState();
}

function readSidebarState(key) {
    const storageKey = sidebarStorageKeys[key];

    if (storageKey === undefined || typeof window === 'undefined') {
        return null;
    }

    try {
        const value = window.localStorage.getItem(storageKey);

        return value === null ? null : value === 'true';
    } catch (error) {
        return null;
    }
}

function writeSidebarState(key, open) {
    const storageKey = sidebarStorageKeys[key];

    if (storageKey === undefined || typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(storageKey, String(open));
    } catch (error) {
        // localStorage unavailable (private mode, full storage) - fall back to route-aware defaults.
    }
}

window.sidebarMenu = (key, activeOnLoad) => ({
    [`${key}Open`]: Boolean(activeOnLoad || readSidebarState(key)),
    toggle() {
        this[`${key}Open`] = !this[`${key}Open`];
        writeSidebarState(key, this[`${key}Open`]);
    },
});

window.sidebarDrawer = () => ({
    sidebarOpen: false,
    open() {
        this.sidebarOpen = true;
    },
    close() {
        this.sidebarOpen = false;
    },
    toggleDrawer() {
        this.sidebarOpen = !this.sidebarOpen;
    },
    init() {
        const desktopQuery = window.matchMedia('(min-width: 1024px)');

        desktopQuery.addEventListener('change', () => {
            if (desktopQuery.matches) {
                this.close();
            }
        });
    },
});

const sidebarScrollStorageKey = 'sidebar.scrollTop';

function readSidebarScrollPosition() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const value = window.sessionStorage.getItem(sidebarScrollStorageKey);

        if (value === null || value === '') {
            return null;
        }

        const parsed = Number(value);

        return Number.isFinite(parsed) && parsed >= 0 ? Math.floor(parsed) : null;
    } catch (error) {
        return null;
    }
}

function writeSidebarScrollPosition(scrollTop) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(sidebarScrollStorageKey, String(Math.max(0, Math.floor(scrollTop))));
    } catch (error) {
        // sessionStorage unavailable (private mode, full storage) - sidebar stays usable without it.
    }
}

window.sidebarScrollPosition = () => ({
    lastSaved: -1,
    init() {
        const saved = readSidebarScrollPosition();

        if (saved === null) {
            return;
        }

        // Apply after the submenu open states have been restored via their own
        // Alpine components, so the nav height is final before we scroll.
        this.$nextTick(() => {
            this.$el.scrollTop = saved;
            this.lastSaved = this.$el.scrollTop;
            writeSidebarScrollPosition(this.lastSaved);
        });
    },
    onScroll() {
        const scrollTop = this.$el.scrollTop;

        if (scrollTop === this.lastSaved) {
            return;
        }

        this.lastSaved = scrollTop;
        writeSidebarScrollPosition(scrollTop);
    },
});

const examFiltersStorageKey = 'annur.examEligibility.filters';

function writeExamFilters(filters) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(examFiltersStorageKey, JSON.stringify({
            search: typeof filters.search === 'string' ? filters.search : '',
            jenjang: typeof filters.jenjang === 'string' ? filters.jenjang : '',
            kelas: typeof filters.kelas === 'string' ? filters.kelas : '',
            status: typeof filters.status === 'string' ? filters.status : '',
            tahun: typeof filters.tahun === 'string' ? filters.tahun : '',
        }));
    } catch (error) {
        // localStorage unavailable (private mode, full storage) - filters simply do not persist.
    }
}

window.addEventListener('livewire:init', () => {
    Livewire.on('examFiltersPersist', (filters) => {
        writeExamFilters(filters);
    });
});

// Keeps the sticky clone of the Rekap Per Kelas header aligned with the real
// table: the clone only mirrors the horizontal scroll position and copies the
// real column widths so both stay pixel-identical while the page scrolls.
window.classRecapStickyHeader = () => ({
    measureFrame: null,
    cleanupHandlers: null,
    init() {
        this.syncWidths();
        this.cleanupHandlers = this.trackLayout();
    },
    trackLayout() {
        const scroller = this.$refs.recapScroller;
        const stopScrollSync = this.syncHorizontalScroll(scroller);
        const onResize = () => this.scheduleMeasure();
        const onPageLoad = () => this.syncWidths();
        window.addEventListener('resize', onResize);
        window.addEventListener('load', onPageLoad);
        const observer = new MutationObserver(() => this.scheduleMeasure());
        observer.observe(this.$el, { childList: true, subtree: true });

        return () => {
            stopScrollSync();
            window.removeEventListener('resize', onResize);
            window.removeEventListener('load', onPageLoad);
            observer.disconnect();
        };
    },
    syncHorizontalScroll(scroller) {
        if (!scroller) {
            return () => {};
        }

        const onScroll = () => {
            const cloneScroller = this.$refs.cloneScroller;

            if (cloneScroller && scroller.scrollLeft !== cloneScroller.scrollLeft) {
                cloneScroller.scrollLeft = scroller.scrollLeft;
            }
        };
        scroller.addEventListener('scroll', onScroll, { passive: true });

        return () => scroller.removeEventListener('scroll', onScroll);
    },
    scheduleMeasure() {
        if (this.measureFrame !== null) {
            return;
        }

        this.measureFrame = requestAnimationFrame(() => {
            this.measureFrame = null;
            this.syncWidths();
        });
    },
    syncWidths() {
        const realTable = this.$refs.recapTable;
        const cloneTable = this.$el.querySelector('.recap-clone-table');

        if (!realTable || !cloneTable) {
            return;
        }

        const realWidth = realTable.offsetWidth;
        cloneTable.style.width = `${realWidth}px`;
        cloneTable.style.minWidth = `${realWidth}px`;

        const realCells = realTable.querySelectorAll('thead tr:last-child th');
        const cloneCells = cloneTable.querySelectorAll('thead tr:last-child th');
        realCells.forEach((cell, index) => {
            const cloneCell = cloneCells[index];

            if (!cloneCell) {
                return;
            }

            const width = cell.getBoundingClientRect().width;
            cloneCell.style.width = `${width}px`;
            cloneCell.style.minWidth = `${width}px`;
        });
    },
    destroy() {
        if (this.cleanupHandlers) {
            this.cleanupHandlers();
        }

        if (this.measureFrame !== null) {
            cancelAnimationFrame(this.measureFrame);
        }
    },
});
