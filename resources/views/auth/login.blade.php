<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Annur Management - Login</title>
    <link rel="icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:FILL@0..1" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f7f9fb 0%, #e0e3e5 100%);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow:
                0 10px 25px -5px rgba(0, 0, 0, 0.05),
                0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }
    </style>
</head>

<body class="min-h-screen flex flex-col items-center justify-center p-4 md:p-8 relative overflow-hidden">

    <!-- Background -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-slate-900 opacity-5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-blue-600 opacity-5 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Flash Alert -->
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            x-init="setTimeout(() => show = false, 3000)"
            class="fixed top-4 right-4 z-[9999] max-w-sm bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 shadow-lg flex items-center gap-3"
        >
            <span class="material-symbols-outlined text-green-600 text-[20px]">check_circle</span>
            <span class="text-sm flex-1">{{ session('success') }}</span>
            <button @click="show = false" class="text-green-400 hover:text-green-600">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    @endif


    <main class="w-full max-w-md z-10">

        <!-- Branding -->
        <div class="text-center mb-8">

            <img src="{{ asset('images/annur_logo2.png') }}" alt="Annur" class="h-16 w-auto max-w-20 object-contain mx-auto mb-4">

            <h1 class="text-2xl font-semibold text-slate-900 mb-2">
                Annur Management
            </h1>

            <p class="text-sm text-slate-600">
                Sistem Administrasi Sekolah
            </p>

        </div>


        <!-- Login Card -->
        <div class="glass-card rounded-xl p-8 w-full">

            <div class="mb-6 text-center">
                <p class="text-sm text-slate-600">
                    Selamat datang kembali, silakan masuk ke akun Anda.
                </p>
            </div>


            <form method="POST" action="{{ route('login') }}" class="space-y-6">

                @csrf


                <!-- Email -->
                <div class="space-y-2">

                    <label
                        for="email"
                        class="block text-sm font-medium text-slate-900"
                    >
                        Email / Username
                    </label>

                    <div class="relative">

                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-500 text-[20px]">
                                person
                            </span>
                        </div>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="admin@sekolah.edu"
                            required
                            autofocus
                            autocomplete="username"
                            class="block w-full pl-10 pr-3 py-2 h-[44px]
                                   border border-slate-300
                                   rounded-lg
                                   bg-white
                                   text-slate-900
                                   text-sm
                                   focus:ring-2
                                   focus:ring-blue-500
                                   focus:border-blue-500
                                   transition-colors"
                        >

                    </div>

                    @error('email')
                        <p class="text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                <!-- Password -->
                <div class="space-y-2">

                    <label
                        for="password"
                        class="block text-sm font-medium text-slate-900"
                    >
                        Password
                    </label>

                    <div class="relative" x-data="{ showPassword: false }">

                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-500 text-[20px]">
                                lock
                            </span>
                        </div>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                            class="block w-full pl-10 pr-12 py-2 h-[44px]
                                   border border-slate-300
                                   rounded-lg
                                   bg-white
                                   text-slate-900
                                   text-sm
                                   focus:ring-2
                                   focus:ring-blue-500
                                   focus:border-blue-500
                                   transition-colors"
                        >

                        <button
                            id="password-visibility-toggle"
                            type="button"
                            @click="showPassword = ! showPassword"
                            :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                            :aria-pressed="showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 z-10 p-1 flex items-center text-slate-500 hover:text-slate-700 focus:outline-none focus:text-blue-600 cursor-pointer pointer-events-auto transition-colors"
                        >
                            <span class="material-symbols-outlined text-[20px]" x-show="! showPassword">visibility</span>
                            <span class="material-symbols-outlined text-[20px]" x-show="showPassword" x-cloak>visibility_off</span>
                        </button>

                    </div>

                    @error('password')
                        <p class="text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>


                <!-- Remember + Forgot -->
                <div class="flex items-center justify-between">

                    <label class="flex items-center cursor-pointer">

                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            class="h-4 w-4 text-blue-600
                                   focus:ring-blue-500
                                   border-slate-300
                                   rounded"
                        >

                        <span class="ml-2 text-sm text-slate-600">
                            Ingat Saya
                        </span>

                    </label>


                    <span></span>

                </div>


                <!-- Button -->
                <div>

                    <button
                        type="submit"
                        class="w-full flex justify-center items-center
                               py-2.5 px-4
                               rounded-lg
                               shadow-sm
                               font-medium
                               text-sm
                               text-white
                               bg-blue-500
                               hover:bg-blue-600
                               focus:outline-none
                               focus:ring-2
                               focus:ring-blue-500
                               focus:ring-offset-2
                               transition-colors
                               h-[44px]"
                    >
                        Masuk

                        <span class="material-symbols-outlined ml-2 text-[20px]">
                            arrow_forward
                        </span>

                    </button>

                </div>

            </form>

        </div>

    </main>


    <!-- Footer -->
    <footer class="absolute text-center w-full max-w-md mx-auto z-10 pb-8 bottom-0">

        <div class="flex justify-center items-center gap-4 text-sm text-slate-500">

            <span>
                © {{ date('Y') }} Annur Management.
            </span>

            <a href="#" class="hover:text-slate-900 transition-colors">
                Bantuan
            </a>

            <a href="#" class="hover:text-slate-900 transition-colors">
                Privasi
            </a>

        </div>

    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => window.Alpine?.start());
    </script>

</body>
</html>
