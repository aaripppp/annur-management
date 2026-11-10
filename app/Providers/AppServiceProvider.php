<?php

namespace App\Providers;

use App\Http\Middleware\AddPrivateResponseHeaders;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RestrictTuUnitAccess;
use App\Models\JemputanDriver;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\User;
use App\Policies\JemputanDriverPolicy;
use App\Policies\PpdbDocumentPolicy;
use App\Policies\ProspectiveStudentPolicy;
use App\Policies\StudentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        RateLimiter::for('ppdb-public-register', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('ppdb-public-success', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        Livewire::addPersistentMiddleware(AddPrivateResponseHeaders::class);
        Livewire::addPersistentMiddleware(EnsureActiveUser::class);
        Livewire::addPersistentMiddleware(RestrictTuUnitAccess::class);

        Gate::policy(PpdbDocument::class, PpdbDocumentPolicy::class);
        Gate::policy(JemputanDriver::class, JemputanDriverPolicy::class);
        Gate::policy(ProspectiveStudent::class, ProspectiveStudentPolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::define('manage-accounts', static fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('change-student-status', static fn (User $user): bool => $user->isAdmin()
            || $user->isSuperAdmin()
            || ($user->isTuUnit() && $user->school_level !== null));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => null);
    }
}
