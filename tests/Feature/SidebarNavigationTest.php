<?php

use App\Models\DaycareChild;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/*
 |--------------------------------------------------------------------------
 | Sidebar navigation reorganization
 |--------------------------------------------------------------------------
 |
 | Siswa and Daycare become collapsible parent groups with submenus, plus a
 | Master Data group. These tests guard the sidebar structure, the route- and
 | query-derived open/active states, and the removal of duplicate top-level
 | links.
 |
 */

function sidebarTestUser(): User
{
    return User::factory()->create();
}

function sidebarActiveChildAnchor(string $href): string
{
    return 'class="bg-secondary-container text-on-secondary-container font-bold rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="'.$href.'"';
}

function sidebarSeeCount(TestResponse $response, string $needle): int
{
    return substr_count($response->getContent(), $needle);
}

function sidebarGroupOpenCount(TestResponse $response, string $group, bool $open): int
{
    return substr_count($response->getContent(), "x-data=\"sidebarMenu('".$group."', ".($open ? 'true' : 'false').')"');
}

const SIDEBAR_ACTIVE_CHILD_CLASS = 'bg-secondary-container text-on-secondary-container font-bold rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200';

/*
 |--------------------------------------------------------------------------
 | 1. Structure renders on every authenticated layout page
 |--------------------------------------------------------------------------
 */
it('renders the reorganized sidebar structure on the dashboard', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('id="nav-student-toggle"', false);
    $response->assertSee('id="nav-student-panel"', false);
    $response->assertSee('id="nav-daycare-toggle"', false);
    $response->assertSee('id="nav-daycare-panel"', false);
    $response->assertSee('id="nav-master-data-toggle"', false);
    $response->assertSee('id="nav-master-data-panel"', false);

    $response->assertSee(':aria-expanded="siswaOpen ? \'true\' : \'false\'"', false);
    $response->assertSee(':aria-expanded="daycareOpen ? \'true\' : \'false\'"', false);
    $response->assertSee(':aria-expanded="masterDataOpen ? \'true\' : \'false\'"', false);

    $response->assertSee('Master Data', false);
    $response->assertSee('Pengaturan', false);
    $response->assertSee('Keluar', false);

    $response->assertSee('href="'.route('siswa.index').'"', false);
    $response->assertSee('href="'.route('siswa.exam-eligibility').'"', false);
    $response->assertSee('href="'.route('pembayaran.index').'"', false);
    $response->assertSee('href="'.route('laporan.index').'"', false);
    $response->assertSee('href="'.route('daycare.index').'"', false);
    $response->assertSee('href="'.route('daycare.payment.entry').'"', false);
    $response->assertSee('href="'.route('daycare.report.daily').'"', false);
    $response->assertSee('href="'.route('bank.index').'"', false);
    $response->assertSee('href="'.route('jenis-pembayaran.index').'"', false);
    $response->assertSee('href="'.route('tarif-pembayaran.index').'"', false);
    $response->assertSee('href="'.route('kelas.index').'"', false);
    $response->assertSee('href="'.route('tahun-ajaran.index').'"', false);
});

it('removes the duplicate top-level pembayaran and laporan links', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('dashboard'));

    expect(sidebarSeeCount($response, 'href="'.route('pembayaran.index').'"'))->toBe(1);
    expect(sidebarSeeCount($response, 'href="'.route('laporan.index').'"'))->toBe(1);
});

it('collapses every parent group on the dashboard by default', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('dashboard'));

    expect(sidebarGroupOpenCount($response, 'siswa', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'daycare', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'siswa', true))->toBe(0);
    expect(sidebarGroupOpenCount($response, 'daycare', true))->toBe(0);
    expect(sidebarGroupOpenCount($response, 'masterData', true))->toBe(0);
});

/*
 |--------------------------------------------------------------------------
 | 2. Siswa group: route-derived open/active state
 |--------------------------------------------------------------------------
 */
it('opens the siswa group with Data Siswa active on the student page', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('siswa.index'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'siswa', true))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'daycare', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('siswa.index')), false);
});

it('opens the siswa group with Kelayakan Ujian active on the eligibility page', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('siswa.exam-eligibility'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'siswa', true))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('siswa.exam-eligibility')), false);
});

it('opens the siswa group with Pembayaran active on the payment page', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('pembayaran.index'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'siswa', true))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'daycare', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('pembayaran.index')), false);
});

it('opens the siswa group with Laporan active on the school report page', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('laporan.index'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'siswa', true))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'daycare', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('laporan.index')), false);
});

/*
 |--------------------------------------------------------------------------
 | 3. Daycare group: route-derived open/active state (no tab query mapping)
 |--------------------------------------------------------------------------
 */
it('opens the daycare group with Data Daycare active by default', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.index'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'daycare', true))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'siswa', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('daycare.index')), false);
});

it('keeps Data Daycare active regardless of a stray tab query', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.index', ['tab' => 'data']));

    $response->assertOk();
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('daycare.index')), false);
});

it('does not map a stray daycare history query to the Pembayaran page', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.index', ['tab' => 'history']));

    $response->assertOk();
    $response->assertSee(sidebarActiveChildAnchor(route('daycare.index')), false);
    $response->assertDontSee(sidebarActiveChildAnchor(route('daycare.payment.entry')), false);
});

it('marks Data Daycare active on the daycare child detail page', function () {
    $child = DaycareChild::factory()->create();

    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.show', $child));

    $response->assertOk();
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('daycare.index')), false);
});

it('opens the daycare group with Pembayaran active on the payment entry route', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.payment.entry'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'daycare', true))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'siswa', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('daycare.payment.entry')), false);
});

it('opens the daycare group with Laporan active on the report page route', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.report.daily'));

    $response->assertOk();
    expect(sidebarGroupOpenCount($response, 'daycare', true))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'siswa', false))->toBe(1);
    expect(sidebarGroupOpenCount($response, 'masterData', false))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('daycare.report.daily')), false);
});

it('links the daycare Laporan entry to the daily report page', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('daycare.index'));

    $response->assertSee('href="'.route('daycare.report.daily').'"', false);
});

/*
 |--------------------------------------------------------------------------
 | 4. Master Data group: existing pages remain reachable and highlighted
 |--------------------------------------------------------------------------
 */
it('opens the master data group with the correct child active on each page', function () {
    $this->actingAs(sidebarTestUser());

    $cases = [
        route('bank.index') => route('bank.index'),
        route('jenis-pembayaran.index') => route('jenis-pembayaran.index'),
        route('tarif-pembayaran.index') => route('tarif-pembayaran.index'),
        route('kelas.index') => route('kelas.index'),
        route('tahun-ajaran.index') => route('tahun-ajaran.index'),
    ];

    foreach ($cases as $pageUrl => $activeHref) {
        $response = $this->get($pageUrl);

        $response->assertOk();
        expect(sidebarGroupOpenCount($response, 'masterData', true))->toBe(1);
        expect(sidebarGroupOpenCount($response, 'siswa', false))->toBe(1);
        expect(sidebarGroupOpenCount($response, 'daycare', false))->toBe(1);
        expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
        $response->assertSee(sidebarActiveChildAnchor($activeHref), false);
    }
});

/*
 |--------------------------------------------------------------------------
 | 5. Persisted sidebar state: localStorage wiring stays independent per parent
 |--------------------------------------------------------------------------
 */
it('wires every sidebar parent to the persisted state component', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('dashboard'));

    expect(sidebarSeeCount($response, 'x-data="sidebarMenu('))->toBe(3);
    expect(sidebarSeeCount($response, "x-data=\"sidebarMenu('siswa'"))->toBe(1);
    expect(sidebarSeeCount($response, "x-data=\"sidebarMenu('daycare'"))->toBe(1);
    expect(sidebarSeeCount($response, "x-data=\"sidebarMenu('masterData'"))->toBe(1);
    expect(sidebarSeeCount($response, '@click="toggle()"'))->toBe(3);
});

it('keeps submenu state independent from active-route highlighting', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('siswa.index'));

    $response->assertSee(":aria-expanded=\"siswaOpen ? 'true' : 'false'\"", false);
    expect(sidebarSeeCount($response, 'x-show="siswaOpen"'))->toBe(1);
    expect(sidebarSeeCount($response, ":class=\"siswaOpen ? 'rotate-180' : ''\""))->toBe(1);
    expect(sidebarSeeCount($response, SIDEBAR_ACTIVE_CHILD_CLASS))->toBe(1);
    $response->assertSee(sidebarActiveChildAnchor(route('siswa.index')), false);
});

it('persists each sidebar parent under its own storage key', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)->toContain('sidebar.siswa.open');
    expect($source)->toContain('sidebar.daycare.open');
    expect($source)->toContain('sidebar.masterData.open');
    expect($source)->toContain('window.sidebarMenu');
});

it('wires the sidebar nav to the scroll position component', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('tahun-ajaran.index'));

    $response->assertOk();
    $response->assertSee('x-data="sidebarScrollPosition()"', false);
    $response->assertSee('@scroll.passive="onScroll()"', false);
    $response->assertSee('flex-1 flex flex-col gap-1 overflow-y-auto', false);
});

it('persists the sidebar scroll position in sessionStorage', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)->toContain('sidebar.scrollTop');
    expect($source)->toContain('sessionStorage');
    expect($source)->toContain('window.sidebarScrollPosition');
    expect($source)->toContain('readSidebarScrollPosition');
    expect($source)->toContain('writeSidebarScrollPosition');
});

/*
 |--------------------------------------------------------------------------
 | 6. Fresh-login sidebar reset
 |--------------------------------------------------------------------------
 */
it('emits the reset meta tag on the first authenticated page load of a session', function () {
    $this->actingAs(sidebarTestUser());

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('<meta name="sidebar-reset" content="1">', false);
});

it('omits the reset meta tag on subsequent page loads within the same session', function () {
    $this->actingAs(sidebarTestUser());

    $this->get(route('dashboard'))->assertOk();

    $response = $this->get(route('siswa.index'));

    $response->assertOk();
    $response->assertDontSee('sidebar-reset', false);
});

it('defines a clearSidebarState helper that clears all sidebar keys', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)->toContain('function clearSidebarState()');
    expect($source)->toContain('meta[name="sidebar-reset"]');
    expect($source)->toContain('localStorage.removeItem');
    expect($source)->toContain("'sidebar.scrollTop'");
});
