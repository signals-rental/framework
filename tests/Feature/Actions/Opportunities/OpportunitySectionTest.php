<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AssignItemToSection;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateOpportunitySection;
use App\Actions\Opportunities\DeleteOpportunitySection;
use App\Actions\Opportunities\RenameOpportunitySection;
use App\Actions\Opportunities\ReorderOpportunitySections;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\RenameOpportunitySectionData;
use App\Data\Opportunities\ReorderOpportunitySectionsData;
use App\Events\AuditableEvent;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

/*
|--------------------------------------------------------------------------
| M8-3c — custom line-grouping (sections) backend
|--------------------------------------------------------------------------
|
| Sections are plain, NON-event-sourced rows. The whole point of the design is
| that the line -> section link (opportunity_items.section_id) is decoupled from
| the Verbs event stream, so a replay rebuilds the item projection WITHOUT
| erasing section assignments. The headline guarantee is proved by the
| replay-safety test below.
|
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function sectionOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Sectioned']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

function sectionedItem(Opportunity $opportunity): OpportunityItem
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Speaker', 'quantity' => '2', 'unit_price' => 5000,
    ]));

    return $opportunity->refresh()->items()->firstOrFail();
}

it('creates a section against an opportunity', function () {
    $opportunity = sectionOpportunity();

    $data = (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from([
        'name' => 'Audio', 'sort_order' => 2,
    ]));

    expect($data->name)->toBe('Audio')
        ->and($data->sort_order)->toBe(2)
        ->and($data->opportunity_id)->toBe($opportunity->id);

    $this->assertDatabaseHas('opportunity_sections', [
        'id' => $data->id,
        'opportunity_id' => $opportunity->id,
        'name' => 'Audio',
        'sort_order' => 2,
    ]);
});

it('renames a section', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create(['name' => 'Old']);

    $data = (new RenameOpportunitySection)($section, RenameOpportunitySectionData::from(['name' => 'New']));

    expect($data->name)->toBe('New');
    $this->assertDatabaseHas('opportunity_sections', ['id' => $section->id, 'name' => 'New']);
});

it('deletes a section and nulls its items section_id', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = sectionedItem($opportunity);

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
    expect($item->refresh()->section_id)->toBe($section->id);

    (new DeleteOpportunitySection)($section);

    $this->assertDatabaseMissing('opportunity_sections', ['id' => $section->id]);
    // nullOnDelete drops the line back to auto-grouping, it is NOT removed.
    expect($item->refresh()->section_id)->toBeNull()
        ->and(OpportunityItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

it('reorders sections by writing sort_order to the supplied index', function () {
    $opportunity = sectionOpportunity();
    $a = OpportunitySection::factory()->for($opportunity)->create(['name' => 'A', 'sort_order' => 0]);
    $b = OpportunitySection::factory()->for($opportunity)->create(['name' => 'B', 'sort_order' => 1]);
    $c = OpportunitySection::factory()->for($opportunity)->create(['name' => 'C', 'sort_order' => 2]);

    $ordered = (new ReorderOpportunitySections)($opportunity, ReorderOpportunitySectionsData::from([
        'section_ids' => [$c->id, $a->id, $b->id],
    ]));

    expect($ordered)->toHaveCount(3)
        ->and($ordered[0]->id)->toBe($c->id)
        ->and($ordered[0]->sort_order)->toBe(0)
        ->and($ordered[1]->id)->toBe($a->id)
        ->and($ordered[1]->sort_order)->toBe(1)
        ->and($ordered[2]->id)->toBe($b->id)
        ->and($ordered[2]->sort_order)->toBe(2);

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2);
});

it('rejects reordering with a section that belongs to another opportunity', function () {
    $opportunity = sectionOpportunity();
    $foreign = OpportunitySection::factory()->create(); // different opportunity

    (new ReorderOpportunitySections)($opportunity, ReorderOpportunitySectionsData::from([
        'section_ids' => [$foreign->id],
    ]));
})->throws(ValidationException::class);

it('assigns a line item to a section via a plain projection write', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = sectionedItem($opportunity);

    $data = (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));

    expect($data->section_id)->toBe($section->id);
    $this->assertDatabaseHas('opportunity_items', ['id' => $item->id, 'section_id' => $section->id]);
});

it('clears a line item section assignment when given a null section_id', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = sectionedItem($opportunity);

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
    expect($item->refresh()->section_id)->toBe($section->id);

    $data = (new AssignItemToSection)($item->refresh(), AssignItemToSectionData::from(['section_id' => null]));

    expect($data->section_id)->toBeNull();
    $this->assertDatabaseHas('opportunity_items', ['id' => $item->id, 'section_id' => null]);
});

it('rejects assigning a line to a section from another opportunity', function () {
    $opportunity = sectionOpportunity();
    $item = sectionedItem($opportunity);
    $foreign = OpportunitySection::factory()->create(); // belongs to a different opportunity

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $foreign->id]));
})->throws(ValidationException::class);

it('does NOT fire any Verbs event when assigning an item to a section', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = sectionedItem($opportunity);

    $eventCountBefore = VerbEvent::query()->count();

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));

    // Section assignment is a plain projection write: it must add NO event to the
    // Verbs stream (otherwise it would not be replay-decoupled).
    expect(VerbEvent::query()->count())->toBe($eventCountBefore);
});

it('rebuilds the item projection from the stream without the section assignment being part of the stream', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create(['name' => 'Audio']);
    $item = sectionedItem($opportunity);

    // Assign the line to the section via the plain (non-event-sourced) action.
    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
    expect($item->refresh()->section_id)->toBe($section->id);

    // Destroy the item projection and rebuild it PURELY from the Verbs stream.
    OpportunityItem::query()->forceDelete();
    expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeFalse();

    Verbs::replay();

    // The row is fully reconstructed by replay (proving section_id never blocks a
    // rebuild). section_id is null here precisely BECAUSE it is decoupled from the
    // stream — the stream carries no section data. The non-destructive replay test
    // below proves the assignment is never erased in the real-world path (where the
    // projection is not truncated).
    $rebuilt = OpportunityItem::query()->whereKey($item->id)->firstOrFail();
    expect($rebuilt->exists)->toBeTrue()
        ->and($rebuilt->section_id)->toBeNull();
});

it('does not erase an existing section_id when the stream is replayed over a live projection (the headline invariant)', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create(['name' => 'Audio']);
    $item = sectionedItem($opportunity);

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
    expect($item->refresh()->section_id)->toBe($section->id);

    // Replay the WHOLE stream WITHOUT truncating the projection first. The item
    // event's handle() re-runs (updateOrCreate by id) — if any event/apply/handle
    // touched section_id it would be overwritten/nulled here. The invariant is
    // that section_id is absent from the event payload, so the existing
    // assignment survives the replay untouched.
    Verbs::replay();

    expect($item->refresh()->section_id)->toBe($section->id);
});

it('writes an audit row for each section mutation', function () {
    $opportunity = sectionOpportunity();

    $created = (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from(['name' => 'Audio']));
    $section = OpportunitySection::query()->whereKey($created->id)->firstOrFail();
    (new RenameOpportunitySection)($section, RenameOpportunitySectionData::from(['name' => 'Sound']));
    (new DeleteOpportunitySection)($section->refresh());

    expect(ActionLog::query()->where('action', 'opportunity_section.created')->count())->toBe(1)
        ->and(ActionLog::query()->where('action', 'opportunity_section.renamed')->count())->toBe(1)
        ->and(ActionLog::query()->where('action', 'opportunity_section.deleted')->count())->toBe(1);
});

it('fires an audit event when a line item is assigned to a section', function () {
    Event::fake([AuditableEvent::class]);

    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = sectionedItem($opportunity);

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));

    Event::assertDispatched(
        AuditableEvent::class,
        fn (AuditableEvent $event): bool => $event->action === 'opportunity.item_section_assigned'
            && $event->newValues === ['section_id' => $section->id],
    );
});

it('denies creating a section without permission', function () {
    $opportunity = sectionOpportunity();

    $this->actingAs(User::factory()->create());

    (new CreateOpportunitySection)($opportunity, CreateOpportunitySectionData::from(['name' => 'Nope']));
})->throws(AuthorizationException::class);

it('denies assigning an item to a section without permission', function () {
    $opportunity = sectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = sectionedItem($opportunity);

    $this->actingAs(User::factory()->create());

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
})->throws(AuthorizationException::class);
