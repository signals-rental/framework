<?php

namespace Tests\Feature\Settings;

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_profile_page_renders_icon_upload_for_own_member(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('settings.profile')
            ->assertSeeLivewire('components.icon-upload')
            ->assertSet('member.id', $user->member_id);
    }

    public function test_member_record_is_created_when_user_has_none(): void
    {
        // The factory auto-links a member; remove it to simulate the legacy gap.
        $user = User::factory()->create();
        Member::whereKey($user->member_id)->delete();
        $user->forceFill(['member_id' => null])->save();

        $this->assertNull($user->fresh()->member_id);

        $this->actingAs($user);

        Volt::test('settings.profile');

        $user->refresh();

        $this->assertNotNull($user->member_id);
        $this->assertSame(MembershipType::User, $user->member->membership_type);
        $this->assertSame($user->name, $user->member->name);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.profile')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors()
            ->assertRedirect(route('settings.profile'));

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Profile saved.', session('status'));
    }

    public function test_updating_profile_name_syncs_linked_member_name(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->member_id);

        $this->actingAs($user);

        Volt::test('settings.profile')
            ->set('name', 'Renamed Person')
            ->set('email', $user->email)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('Renamed Person', $user->member->fresh()->name);
    }

    public function test_profile_photo_update_event_triggers_full_reload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('settings.profile')
            ->call('handleProfilePhotoUpdated')
            ->assertRedirect(route('settings.profile'));
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.profile')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        // A second active user must exist so the deleter is not the last user.
        User::factory()->create();

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        User::factory()->create();

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function test_owner_cannot_delete_their_own_account(): void
    {
        // Another active user so the "last user" guard is not the blocker.
        User::factory()->create();

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner);

        $response = Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($owner->fresh());
    }

    public function test_delete_form_shows_message_and_hides_button_for_owner(): void
    {
        User::factory()->create();

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner);

        Volt::test('settings.delete-user-form')
            ->assertSee('Transfer ownership before deleting your account.')
            ->assertDontSee("open-modal', 'confirm-user-deletion", false);
    }

    public function test_last_active_user_cannot_delete_their_account(): void
    {
        // Only one active user in the system (plus deactivated users which do not count).
        User::factory()->deactivated()->create();

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }

    public function test_last_active_user_sees_blocking_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('settings.delete-user-form')
            ->assertSee('You are the last user in the system and cannot delete your account.');
    }
}
