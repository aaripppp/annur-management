<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pendaftaran Peserta Didik Baru YPI Nurrahim An-nur">
    <title>{{ $title ?? 'PPDB Online - YPI Nurrahim An-nur' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,0" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-950 antialiased">
    <div class="relative min-h-screen overflow-x-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-primary sm:h-72"></div>
        <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-secondary/20 blur-3xl pointer-events-none"></div>

        <header class="relative z-10 border-b border-white/15">
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6 sm:py-4">
                <div class="h-11 w-11 shrink-0 rounded-xl bg-white p-1.5 shadow-sm sm:h-12 sm:w-12">
                    <img src="{{ asset('images/annur_logo2.png') }}" alt="Logo YPI Nurrahim An-nur" class="h-full w-full object-contain">
                </div>
                <div>
                    <p class="text-sm font-semibold text-white sm:text-base">YPI Nurrahim An-nur</p>
                    <p class="text-xs leading-5 text-slate-200 sm:text-sm">Penerimaan Peserta Didik Baru Online</p>
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-6xl px-3 py-5 sm:px-6 sm:py-10">
            {{ $slot }}
        </main>

        <footer class="relative z-10 mx-auto max-w-6xl px-4 pb-8 text-center text-xs leading-5 text-slate-600 sm:px-6 sm:text-sm">
            Data yang diisi pada halaman ini digunakan untuk proses pendaftaran PPDB.
        </footer>
    </div>
    <script>
        (function () {
            function motionBehavior() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
            }

            function focusWithoutScrolling(target) {
                if (typeof target.focus !== 'function') return;

                try {
                    target.focus({ preventScroll: true });
                } catch (error) {
                    target.focus();
                }
            }

            function scrollToPpdbStep() {
                const stepSection = document.querySelector('[data-step-section]');
                if (!stepSection) return;

                stepSection.scrollIntoView({ behavior: motionBehavior(), block: 'start' });
                focusWithoutScrolling(stepSection);
            }

            function scrollToPpdbFirstError() {
                const stepSection = document.querySelector('[data-step-section]');
                if (!stepSection) return;

                const invalidControl = stepSection.querySelector('[aria-invalid="true"]');
                const target = invalidControl || stepSection.querySelector('[data-error-field]');
                if (!target) return;

                target.scrollIntoView({ behavior: motionBehavior(), block: 'center' });
                if (!invalidControl) return;

                const focusTarget = invalidControl.matches('input, select, textarea, button')
                    ? invalidControl
                    : invalidControl.querySelector('input, select, textarea, button');
                if (focusTarget) focusWithoutScrolling(focusTarget);
            }

            window.addEventListener('ppdb-step-changed', () => requestAnimationFrame(scrollToPpdbStep));
            window.addEventListener('ppdb-validation-failed', () => requestAnimationFrame(scrollToPpdbFirstError));
        })();
    </script>
    @livewireScripts
</body>
</html>
