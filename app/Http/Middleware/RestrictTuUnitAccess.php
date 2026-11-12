<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

class RestrictTuUnitAccess
{
    /**
     * Livewire components TU may interact with through the shared update endpoint.
     *
     * @var list<string>
     */
    private const ALLOWED_LIVEWIRE_COMPONENTS = [
        'student-management',
        'tu-student-biodata',
        'student-detail',
        'prospective-student-management',
        'tu-prospective-student-biodata',
        'prospective-student-detail',
        'ppdb-applicant-editor',
        'prospective-student-bulk-update',
        'ppdb-document-replacer',
        'school-daily-report',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|Redirector)  $next
     */
    public function handle(Request $request, Closure $next): Response|Redirector
    {
        $user = $request->user();

        if ($user === null || ! $user->isTuUnit()) {
            return $next($request);
        }

        if ($request->routeIs('default.livewire.update')) {
            if (! $this->usesOnlyAllowedLivewireComponents($request)) {
                abort(403);
            }

            return $next($request);
        }

        if ($request->routeIs(
            'tu.pending',
            'logout',
            'profile.edit',
            'profile.update',
            'password.update',
            'siswa.index',
            'siswa.biodata',
            'siswa.show',
            'siswa.bills.pdf',
            'calon-siswa.index',
            'calon-siswa.biodata',
            'calon-siswa.show',
            'calon-siswa.biodata.pdf',
            'calon-siswa.biodata-recap.export',
            'calon-siswa.update',
            'ppdb.documents.preview',
            'ppdb.documents.download',
            'laporan.index',
            'laporan.kelas.export',
            'laporan.kelas.unit.export',
        )) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->route('tu.pending');
        }

        abort(403);
    }

    private function usesOnlyAllowedLivewireComponents(Request $request): bool
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
                return false;
            }

            $snapshot = json_decode($component['snapshot'], true);

            if (! is_array($snapshot) || ! in_array($snapshot['memo']['name'] ?? null, self::ALLOWED_LIVEWIRE_COMPONENTS, true)) {
                return false;
            }
        }

        return true;
    }
}
