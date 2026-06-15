<?php

use App\Models\Activity;
use App\Models\Email;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Phone;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('registers the signals:clear-demo command', function () {
    expect(Artisan::all())->toHaveKey('signals:clear-demo');
});

it('warns when no demo data has been seeded', function () {
    $this->artisan('signals:clear-demo')
        ->assertSuccessful()
        ->expectsOutputToContain('No demo data');
});

it('removes demo stores tagged with demo-data and leaves untagged stores', function () {
    // Demo stores are tagged 'demo-data' by DemoDataSeeder::createDemoStores();
    // clear-demo selects them by tag, not by name.
    Store::factory()->create(['name' => 'London Warehouse', 'is_default' => false, 'tag_list' => ['demo-data']]);
    Store::factory()->create(['name' => 'Manchester Depot', 'is_default' => false, 'tag_list' => ['demo-data']]);
    Store::factory()->create(['name' => 'Edinburgh Office', 'is_default' => false, 'tag_list' => ['demo-data']]);
    Store::factory()->create(['name' => 'My Real Store', 'is_default' => true, 'tag_list' => []]);
    // A store named like a demo store but NOT tagged must survive.
    Store::factory()->create(['name' => 'London Warehouse', 'is_default' => false, 'tag_list' => ['real']]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 3 demo stores');

    expect(Store::whereJsonContains('tag_list', 'demo-data')->exists())->toBeFalse();
    expect(Store::where('name', 'My Real Store')->exists())->toBeTrue();
    expect(Store::where('name', 'London Warehouse')->whereJsonContains('tag_list', 'real')->exists())->toBeTrue();
});

it('force-removes demo products tagged with demo-data and leaves real products', function () {
    $demoProduct = Product::factory()->create(['name' => 'Demo - LED PAR Can', 'tag_list' => ['demo-data']]);
    $realProduct = Product::factory()->create(['name' => 'Real Product', 'tag_list' => ['catalogue']]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 1 demo products');

    // Products soft-delete, so demo rows must be gone even withTrashed().
    expect(Product::withTrashed()->find($demoProduct->id))->toBeNull();
    expect(Product::find($realProduct->id))->not->toBeNull();
});

it('removes a demo store tagged with demo-data created by the seeder', function () {
    // Mirror DemoDataSeeder::createDemoStores() — demo stores carry the
    // demo-data tag so they are identifiable as demo records.
    $demoStore = Store::factory()->create([
        'name' => 'London Warehouse',
        'is_default' => false,
        'tag_list' => ['demo-data'],
    ]);
    Store::factory()->create(['name' => 'My Real Store', 'is_default' => true]);

    expect($demoStore->fresh()->tag_list)->toContain('demo-data');

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Store::find($demoStore->id))->toBeNull();
    expect(Store::where('name', 'My Real Store')->exists())->toBeTrue();
});

it('cancels when user declines interactive confirmation', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    Store::factory()->create(['name' => 'London Warehouse', 'is_default' => false]);

    $this->artisan('signals:clear-demo')
        ->expectsConfirmation('This will remove all demo data. Continue?', 'no')
        ->assertSuccessful()
        ->expectsOutputToContain('Cancelled');

    // Demo data should NOT have been removed
    expect(Store::where('name', 'London Warehouse')->exists())->toBeTrue();
    expect(settings('setup.demo_seeded_at'))->not->toBeEmpty();
});

it('clears the demo_seeded_at setting', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(settings('setup.demo_seeded_at'))->toBeEmpty();
});

it('removes demo members tagged with demo-data', function () {
    $demoMember = Member::factory()->create(['tag_list' => ['demo-data']]);
    $realMember = Member::factory()->create(['tag_list' => ['vip']]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Member::withTrashed()->find($demoMember->id))->toBeNull();
    expect(Member::find($realMember->id))->not->toBeNull();
});

it('removes emails associated with demo members', function () {
    $demoMember = Member::factory()->create(['tag_list' => ['demo-data']]);
    $realMember = Member::factory()->create(['tag_list' => []]);

    $demoEmail = Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $demoMember->id,
    ]);
    $realEmail = Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $realMember->id,
    ]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Email::find($demoEmail->id))->toBeNull();
    expect(Email::find($realEmail->id))->not->toBeNull();
});

it('removes phones associated with demo members', function () {
    $demoMember = Member::factory()->create(['tag_list' => ['demo-data']]);
    $realMember = Member::factory()->create(['tag_list' => []]);

    $demoPhone = Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $demoMember->id,
    ]);
    $realPhone = Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $realMember->id,
    ]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(Phone::find($demoPhone->id))->toBeNull();
    expect(Phone::find($realPhone->id))->not->toBeNull();
});

it('removes member relationships involving demo members', function () {
    $demoMember = Member::factory()->create(['tag_list' => ['demo-data']]);
    $realMember1 = Member::factory()->create(['tag_list' => []]);
    $realMember2 = Member::factory()->create(['tag_list' => []]);

    $demoRelationship = MemberRelationship::factory()->create([
        'member_id' => $demoMember->id,
        'related_member_id' => $realMember1->id,
    ]);
    $realRelationship = MemberRelationship::factory()->create([
        'member_id' => $realMember1->id,
        'related_member_id' => $realMember2->id,
    ]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful();

    expect(MemberRelationship::find($demoRelationship->id))->toBeNull();
    expect(MemberRelationship::find($realRelationship->id))->not->toBeNull();
});

it('confirms interactively and proceeds when user accepts', function () {
    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    Store::factory()->create(['name' => 'London Warehouse', 'is_default' => false, 'tag_list' => ['demo-data']]);

    $this->artisan('signals:clear-demo')
        ->expectsConfirmation('This will remove all demo data. Continue?', 'yes')
        ->assertSuccessful()
        ->expectsOutputToContain('Demo data removed');

    expect(Store::whereJsonContains('tag_list', 'demo-data')->exists())->toBeFalse();
});

it('outputs count of removed demo members', function () {
    Member::factory()->count(3)->create(['tag_list' => ['demo-data']]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 3 demo members');
});

it('removes demo activities tagged with demo-data and leaves real activities', function () {
    $demoActivity = Activity::factory()->create(['subject' => 'Demo follow-up', 'tag_list' => ['demo-data']]);
    $realActivity = Activity::factory()->create(['subject' => 'Real follow-up', 'tag_list' => ['crm']]);

    settings()->set('setup.demo_seeded_at', now()->toIso8601String());

    $this->artisan('signals:clear-demo', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Removed 1 demo activities');

    expect(Activity::find($demoActivity->id))->toBeNull();
    expect(Activity::find($realActivity->id))->not->toBeNull();
});
