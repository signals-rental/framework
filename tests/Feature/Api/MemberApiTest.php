<?php

use App\Models\Address;
use App\Models\Email;
use App\Models\Link;
use App\Models\Member;
use App\Models\Phone;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/members', function () {
    it('lists members with pagination meta', function () {
        Member::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members')
            ->assertOk()
            ->assertJsonStructure([
                'members' => [
                    '*' => ['id', 'name', 'membership_type', 'is_active', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by membership_type', function () {
        Member::factory()->organisation()->create();
        Member::factory()->contact()->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?q[membership_type_eq]=organisation')
            ->assertOk();

        expect($response->json('members'))->toHaveCount(1);
        expect($response->json('members.0.membership_type'))->toBe('organisation');
    });

    it('includes addresses when requested', function () {
        $member = Member::factory()->create();
        Address::factory()->for($member, 'addressable')->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?include=addresses')
            ->assertOk();

        expect($response->json('members.0.addresses'))->toBeArray()->toHaveCount(1);
    });

    it('requires members:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members')
            ->assertForbidden();
    });
});

describe('GET /api/v1/members/{id}', function () {
    it('shows a single member', function () {
        $member = Member::factory()->create(['name' => 'Test Org']);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('member.name', 'Test Org');
    });
});

describe('POST /api/v1/members', function () {
    it('creates a member', function () {
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', [
                'name' => 'Acme Corp',
                'membership_type' => 'organisation',
            ])
            ->assertCreated()
            ->assertJsonPath('member.name', 'Acme Corp')
            ->assertJsonPath('member.membership_type', 'organisation');

        $this->assertDatabaseHas('members', ['name' => 'Acme Corp']);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/members', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'membership_type']);
    });
});

describe('PUT /api/v1/members/{id}', function () {
    it('updates a member', function () {
        $member = Member::factory()->create(['name' => 'Old Name']);
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$member->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('member.name', 'New Name');
    });
});

describe('DELETE /api/v1/members/{id}', function () {
    it('soft-deletes a member', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('members', ['id' => $member->id]);
        expect(Member::withTrashed()->find($member->id)->trashed())->toBeTrue();
    });
});

describe('nested contact details', function () {
    it('creates and lists addresses for a member', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:write', 'members:read'])->plainTextToken;

        // Create
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/members/{$member->id}/addresses", [
                'street' => '123 Main St',
                'city' => 'London',
                'postcode' => 'EC1A 1BB',
            ])
            ->assertCreated()
            ->assertJsonPath('address.city', 'London');

        // List
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}/addresses")
            ->assertOk()
            ->assertJsonCount(1, 'addresses');
    });

    it('creates and lists emails for a member', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:write', 'members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/members/{$member->id}/emails", [
                'address' => 'test@example.com',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('email.address', 'test@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}/emails")
            ->assertOk()
            ->assertJsonCount(1, 'emails');
    });

    it('creates and lists phones for a member', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:write', 'members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/members/{$member->id}/phones", [
                'number' => '+44 20 7946 0958',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('phone.number', '+44 20 7946 0958');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}/phones")
            ->assertOk()
            ->assertJsonCount(1, 'phones');
    });

    it('creates and lists links for a member', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:write', 'members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/members/{$member->id}/links", [
                'url' => 'https://example.com',
                'name' => 'Website',
            ])
            ->assertCreated()
            ->assertJsonPath('link.url', 'https://example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}/links")
            ->assertOk()
            ->assertJsonCount(1, 'links');
    });

    it('updates an address for a member', function () {
        $member = Member::factory()->create();
        $address = Address::factory()->for($member, 'addressable')->create(['city' => 'London']);
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$member->id}/addresses/{$address->id}", [
                'city' => 'Manchester',
            ])
            ->assertOk()
            ->assertJsonPath('address.city', 'Manchester');
    });

    it('deletes an address for a member', function () {
        $member = Member::factory()->create();
        $address = Address::factory()->for($member, 'addressable')->create();
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/members/{$member->id}/addresses/{$address->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    });

    it('updates an email for a member', function () {
        $member = Member::factory()->create();
        $email = Email::factory()->for($member, 'emailable')->create(['address' => 'old@example.com']);
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$member->id}/emails/{$email->id}", [
                'address' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('email.address', 'new@example.com');
    });

    it('deletes an email for a member', function () {
        $member = Member::factory()->create();
        $email = Email::factory()->for($member, 'emailable')->create();
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/members/{$member->id}/emails/{$email->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('emails', ['id' => $email->id]);
    });

    it('updates a phone for a member', function () {
        $member = Member::factory()->create();
        $phone = Phone::factory()->for($member, 'phoneable')->create(['number' => '+44 111']);
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$member->id}/phones/{$phone->id}", [
                'number' => '+44 222',
            ])
            ->assertOk()
            ->assertJsonPath('phone.number', '+44 222');
    });

    it('deletes a phone for a member', function () {
        $member = Member::factory()->create();
        $phone = Phone::factory()->for($member, 'phoneable')->create();
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/members/{$member->id}/phones/{$phone->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('phones', ['id' => $phone->id]);
    });

    it('updates a link for a member', function () {
        $member = Member::factory()->create();
        $link = Link::factory()->for($member, 'linkable')->create(['url' => 'https://old.com']);
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/members/{$member->id}/links/{$link->id}", [
                'url' => 'https://new.com',
            ])
            ->assertOk()
            ->assertJsonPath('link.url', 'https://new.com');
    });

    it('deletes a link for a member', function () {
        $member = Member::factory()->create();
        $link = Link::factory()->for($member, 'linkable')->create();
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/members/{$member->id}/links/{$link->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('links', ['id' => $link->id]);
    });
});

describe('member relationships', function () {
    it('creates and lists relationships', function () {
        $contact = Member::factory()->contact()->create();
        $org = Member::factory()->organisation()->create();
        $token = $this->owner->createToken('test', ['members:write', 'members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/members/{$contact->id}/relationships", [
                'related_member_id' => $org->id,
                'relationship_type' => 'employee',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('relationship.relationship_type', 'employee');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$contact->id}/relationships")
            ->assertOk()
            ->assertJsonCount(1, 'relationships');
    });
});
