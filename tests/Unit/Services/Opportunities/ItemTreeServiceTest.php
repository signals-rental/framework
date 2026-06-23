<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Services\Opportunities\ItemTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ItemTreeService;
    // A real parent satisfies the opportunity_id foreign key on projection rows.
    $this->opportunityId = Opportunity::factory()->create()->id;
});

/**
 * Project a bare projection row at the given path/version without firing the
 * event stream — the tree service only reads the `opportunity_items` projection.
 */
function makeTreeRow(int $opportunityId, string $path, ?int $versionId = null): OpportunityItem
{
    return OpportunityItem::factory()->create([
        'opportunity_id' => $opportunityId,
        'version_id' => $versionId,
        'path' => $path,
    ]);
}

it('returns 0001 for the first top-level path in an empty scope', function () {
    expect($this->service->nextTopLevelPath($this->opportunityId, null))->toBe('0001');
});

it('returns 0002 after one existing top-level row', function () {
    makeTreeRow($this->opportunityId, '0001');

    expect($this->service->nextTopLevelPath($this->opportunityId, null))->toBe('0002');
});

it('skips deeper rows when computing the next top-level path', function () {
    makeTreeRow($this->opportunityId, '0001');
    makeTreeRow($this->opportunityId, '00010001'); // child of 0001 — must not bump the top-level counter

    expect($this->service->nextTopLevelPath($this->opportunityId, null))->toBe('0002');
});

it('returns the first child path under a parent', function () {
    makeTreeRow($this->opportunityId, '0001');

    expect($this->service->nextChildPath($this->opportunityId, null, '0001'))->toBe('00010001');
});

it('returns the next child path after an existing child', function () {
    makeTreeRow($this->opportunityId, '0001');
    makeTreeRow($this->opportunityId, '00010001');

    expect($this->service->nextChildPath($this->opportunityId, null, '0001'))->toBe('00010002');
});

it('isolates paths between two versions of the same opportunity', function () {
    $versionA = OpportunityVersion::factory()->create(['opportunity_id' => $this->opportunityId])->id;
    $versionB = OpportunityVersion::factory()->create(['opportunity_id' => $this->opportunityId])->id;

    makeTreeRow($this->opportunityId, '0001', versionId: $versionA);
    makeTreeRow($this->opportunityId, '0002', versionId: $versionA);

    // Version B is empty even though version A has two rows.
    expect($this->service->nextTopLevelPath($this->opportunityId, $versionB))->toBe('0001')
        ->and($this->service->nextTopLevelPath($this->opportunityId, $versionA))->toBe('0003');
});

it('isolates the null-version scope from a numbered version', function () {
    $version = OpportunityVersion::factory()->create(['opportunity_id' => $this->opportunityId])->id;

    makeTreeRow($this->opportunityId, '0001', versionId: null);
    makeTreeRow($this->opportunityId, '0001', versionId: $version);

    expect($this->service->nextTopLevelPath($this->opportunityId, null))->toBe('0002')
        ->and($this->service->nextTopLevelPath($this->opportunityId, $version))->toBe('0002');
});
