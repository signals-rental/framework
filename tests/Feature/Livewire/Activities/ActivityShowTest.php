<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

// ── Regarding — member link ────────────────────────────────────────────────────

it('renders a member regarding as a new-tab link with an avatar photo', function () {
    Storage::fake('public');

    $member = Member::factory()->contact()->create([
        'name' => 'Ada Lovelace',
        'icon_thumb_url' => 'members/icons/ada-thumb.png',
    ]);
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Ada Lovelace')
        ->assertSeeHtml('href="'.route('members.show', $member->id).'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('<img');
});

it('renders a member regarding link with initials when no photo is set', function () {
    $member = Member::factory()->contact()->create([
        'name' => 'Grace Hopper',
        'icon_thumb_url' => null,
    ]);
    $activity = Activity::factory()->forMember($member)->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Grace Hopper')
        ->assertSeeHtml('href="'.route('members.show', $member->id).'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSee('GH');
});

it('renders a non-member regarding as a badge without a member link', function () {
    $product = Product::factory()->create(['name' => 'Par Can 64']);
    $activity = Activity::factory()->forProduct($product)->create();

    Volt::test('activities.show', ['activity' => $activity])
        ->assertSee('Par Can 64')
        ->assertSee('Product')
        ->assertDontSeeHtml('href="'.route('members.show', $product->id).'"');
});
