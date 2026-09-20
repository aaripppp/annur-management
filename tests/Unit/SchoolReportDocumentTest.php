<?php

use App\Enums\SchoolLevel;
use App\Support\SchoolReportDocument;

it('derives the organization-wide unit name for the all-levels report', function () {
    expect(SchoolReportDocument::unitName(null))->toBe('An-Nur');
});

it('derives a level-prefixed unit name per selected school level without IT', function () {
    expect(SchoolReportDocument::unitName(SchoolLevel::TK))
        ->toBe('TK An-Nur')
        ->and(SchoolReportDocument::unitName(SchoolLevel::SD))
        ->toBe('SD An-Nur')
        ->and(SchoolReportDocument::unitName(SchoolLevel::SMP))
        ->toBe('SMP An-Nur')
        ->and(SchoolReportDocument::unitName(SchoolLevel::SMA))
        ->toBe('SMA An-Nur');
});
