<?php

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $default = config('filesystems.default', 'local');
    Storage::fake($default === 'local' ? 'public' : $default);
});

describe('Header user avatar', function () {
    it('renders the avatar image when the user member has an icon thumbnail', function () {
        $member = Member::factory()->user()->create([
            'icon_url' => 'icons/members/1/avatar.jpg',
            'icon_thumb_url' => 'icons/members/1/thumbs/avatar.jpg',
        ]);
        $user = User::factory()->create(['member_id' => $member->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('s-avatar-img', false)
            ->assertSee('icons/members/1/thumbs/avatar.jpg', false);
    });

    it('renders initials when the user member has no icon', function () {
        $member = Member::factory()->user()->create([
            'icon_url' => null,
            'icon_thumb_url' => null,
        ]);
        $user = User::factory()->create([
            'name' => 'Jane Cooper',
            'member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('s-avatar-initials', false)
            ->assertSee('JC', false)
            ->assertDontSee('s-avatar-img', false);
    });
});

describe('Header brand block removed', function () {
    // The company name + logo/letter-square brand block was removed from the
    // top nav (2026-06-12 sprint) — the home icon covers the dashboard link.
    // branding.logo_path / logo_has_transparency remain stored for other
    // surfaces; the header must not render them.
    it('does not render the company name or branding logo in the header', function () {
        settings()->setMany([
            'branding.logo_path' => 'branding/logo.png',
            'company.name' => 'Acme Rentals',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('header-brand', false)
            ->assertDontSee('branding/logo.png', false);
    });
});

describe('Brand contrast variable injection', function () {
    it('injects the derived ink and on-* CSS variables', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--brand-primary-ink:', false)
            ->assertSee('--brand-accent-ink:', false)
            ->assertSee('--brand-on-primary:', false)
            ->assertSee('--brand-on-accent:', false);
    });

    it('darkens a white primary so the ink is not white and on-primary is dark', function () {
        settings()->set('branding.primary_colour', '#ffffff');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $html = $response->getContent();

        preg_match('/--brand-primary-ink:\s*(#[0-9a-fA-F]{6})/', $html, $inkMatch);
        preg_match('/--brand-on-primary:\s*(#[0-9a-fA-F]{6})/', $html, $onMatch);

        expect(strtolower($inkMatch[1]))->not->toBe('#ffffff');
        expect(strtolower($onMatch[1]))->toBe('#0f172a');
    });

    it('leaves the stock navy theme unchanged (ink equals raw)', function () {
        settings()->set('branding.primary_colour', '#1e3a5f');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $html = $response->getContent();

        preg_match('/--brand-primary-ink:\s*(#[0-9a-fA-F]{6})/', $html, $inkMatch);
        preg_match('/--brand-on-primary:\s*(#[0-9a-fA-F]{6})/', $html, $onMatch);

        expect(strtolower($inkMatch[1]))->toBe('#1e3a5f');
        expect(strtolower($onMatch[1]))->toBe('#ffffff');
    });
});
