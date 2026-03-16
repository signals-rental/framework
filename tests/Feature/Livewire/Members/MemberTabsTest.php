<?php

use App\Models\Address;
use App\Models\Email;
use App\Models\Link;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Phone;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

describe('stub tab pages', function () {
    it('renders the quotes tab', function () {
        $member = Member::factory()->create();

        $this->get("/members/{$member->id}/quotes")
            ->assertOk()
            ->assertSee('Quotes Coming Soon');
    });

    it('renders the opportunities tab', function () {
        $member = Member::factory()->create();

        $this->get("/members/{$member->id}/opportunities")
            ->assertOk()
            ->assertSee('Opportunities Coming Soon');
    });

    it('renders the movements tab', function () {
        $member = Member::factory()->create();

        $this->get("/members/{$member->id}/movements")
            ->assertOk()
            ->assertSee('Movements Coming Soon');
    });

    it('renders the invoices tab', function () {
        $member = Member::factory()->create();

        $this->get("/members/{$member->id}/invoices")
            ->assertOk()
            ->assertSee('Invoices Coming Soon');
    });

    it('renders the activities tab', function () {
        $member = Member::factory()->create();

        $this->get("/members/{$member->id}/activities")
            ->assertOk()
            ->assertSee('Activities Coming Soon');
    });

    it('renders the information tab', function () {
        $member = Member::factory()->create();

        $this->get("/members/{$member->id}/information")
            ->assertOk();
    });

    it('renders the contacts tab for an organisation', function () {
        $org = Member::factory()->organisation()->create();

        $this->get("/members/{$org->id}/member-contacts")
            ->assertOk();
    });

    it('renders the contacts tab for a contact', function () {
        $contact = Member::factory()->contact()->create();

        $this->get("/members/{$contact->id}/member-contacts")
            ->assertOk();
    });
});

describe('information tab inline delete actions', function () {
    it('can delete an address from the overview', function () {
        $member = Member::factory()->create();
        $address = Address::factory()->create([
            'addressable_type' => Member::class,
            'addressable_id' => $member->id,
        ]);

        Volt::test('members.information', ['member' => $member])
            ->call('deleteAddress', $address->id);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    });

    it('can delete an email from the overview', function () {
        $member = Member::factory()->create();
        $email = Email::factory()->create([
            'emailable_type' => Member::class,
            'emailable_id' => $member->id,
        ]);

        Volt::test('members.information', ['member' => $member])
            ->call('deleteEmail', $email->id);

        $this->assertDatabaseMissing('emails', ['id' => $email->id]);
    });

    it('can delete a phone from the overview', function () {
        $member = Member::factory()->create();
        $phone = Phone::factory()->create([
            'phoneable_type' => Member::class,
            'phoneable_id' => $member->id,
        ]);

        Volt::test('members.information', ['member' => $member])
            ->call('deletePhone', $phone->id);

        $this->assertDatabaseMissing('phones', ['id' => $phone->id]);
    });

    it('can delete a link from the overview', function () {
        $member = Member::factory()->create();
        $link = Link::factory()->create([
            'linkable_type' => Member::class,
            'linkable_id' => $member->id,
        ]);

        Volt::test('members.information', ['member' => $member])
            ->call('deleteLink', $link->id);

        $this->assertDatabaseMissing('links', ['id' => $link->id]);
    });
});

describe('information tab displays contact details', function () {
    it('shows addresses on the overview', function () {
        $member = Member::factory()->create();
        Address::factory()->create([
            'addressable_type' => Member::class,
            'addressable_id' => $member->id,
            'street' => '123 Test Street',
            'city' => 'London',
        ]);

        Volt::test('members.information', ['member' => $member])
            ->assertSee('123 Test Street')
            ->assertSee('London');
    });

    it('shows emails on the overview', function () {
        $member = Member::factory()->create();
        Email::factory()->create([
            'emailable_type' => Member::class,
            'emailable_id' => $member->id,
            'address' => 'test@example.com',
        ]);

        Volt::test('members.information', ['member' => $member])
            ->assertSee('test@example.com');
    });

    it('shows phones on the overview', function () {
        $member = Member::factory()->create();
        Phone::factory()->create([
            'phoneable_type' => Member::class,
            'phoneable_id' => $member->id,
            'number' => '+44 7700 900123',
        ]);

        Volt::test('members.information', ['member' => $member])
            ->assertSee('+44 7700 900123');
    });

    it('shows links on the overview', function () {
        $member = Member::factory()->create();
        Link::factory()->create([
            'linkable_type' => Member::class,
            'linkable_id' => $member->id,
            'url' => 'https://example.com',
        ]);

        Volt::test('members.information', ['member' => $member])
            ->assertSee('https://example.com');
    });
});

describe('contacts tab with DataTable', function () {
    it('shows contacts for an organisation member', function () {
        $org = Member::factory()->organisation()->create();
        $contact = Member::factory()->contact()->create(['name' => 'Jane Doe']);

        MemberRelationship::factory()->create([
            'member_id' => $contact->id,
            'related_member_id' => $org->id,
        ]);

        $this->get("/members/{$org->id}/member-contacts")
            ->assertOk()
            ->assertSee('Jane Doe');
    });

    it('shows organisations for a contact member', function () {
        $contact = Member::factory()->contact()->create();
        $org = Member::factory()->organisation()->create(['name' => 'Acme Corp']);

        MemberRelationship::factory()->create([
            'member_id' => $contact->id,
            'related_member_id' => $org->id,
        ]);

        $this->get("/members/{$contact->id}/member-contacts")
            ->assertOk()
            ->assertSee('Acme Corp');
    });
});
