<?php

use App\Enums\CustomFieldType;
use App\Models\Address;
use App\Models\Country;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Email;
use App\Models\Link;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\OrganisationTaxClass;
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
                    '*' => ['id', 'name', 'membership_type', 'active', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 4); // 3 created + 1 owner's User-type member
    });

    it('filters by membership_type', function () {
        Member::factory()->organisation()->create();
        Member::factory()->contact()->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?q[membership_type_eq]=organisation')
            ->assertOk();

        expect($response->json('members'))->toHaveCount(1);
        expect($response->json('members.0.membership_type'))->toBe('Organisation');
    });

    it('includes addresses when requested', function () {
        $member = Member::factory()->create();
        Address::factory()->for($member, 'addressable')->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}?include=addresses")
            ->assertOk();

        expect($response->json('member.addresses'))->toBeArray()->toHaveCount(1);
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
            ->assertJsonPath('member.membership_type', 'Organisation');

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

    it('deletes a relationship', function () {
        $contact = Member::factory()->contact()->create();
        $org = Member::factory()->organisation()->create();
        $relationship = \App\Models\MemberRelationship::factory()->create([
            'member_id' => $contact->id,
            'related_member_id' => $org->id,
        ]);
        $token = $this->owner->createToken('test', ['members:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/members/{$contact->id}/relationships/{$relationship->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('member_relationships', ['id' => $relationship->id]);
    });
});

describe('member includes', function () {
    it('includes emails, phones, and links when requested', function () {
        $member = Member::factory()->create();
        Email::factory()->for($member, 'emailable')->create(['address' => 'test@example.com']);
        Phone::factory()->for($member, 'phoneable')->create(['number' => '+44 123']);
        Link::factory()->for($member, 'linkable')->create(['url' => 'https://example.com']);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}?include=emails,phones,links")
            ->assertOk();

        expect($response->json('member.emails'))->toBeArray()->toHaveCount(1)
            ->and($response->json('member.phones'))->toBeArray()->toHaveCount(1)
            ->and($response->json('member.links'))->toBeArray()->toHaveCount(1);
    });
});

describe('default custom_fields in API responses', function () {
    it('includes custom_fields by default in index response', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members')
            ->assertOk();

        expect($response->json('members.0.custom_fields'))->toBeArray();
    });

    it('includes custom_fields by default in show response', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        expect($response->json('member.custom_fields'))->toBeArray();
    });

    it('returns actual custom field values when they exist', function () {
        $member = Member::factory()->create();
        $field = CustomField::factory()->create([
            'name' => 'po_reference',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);
        CustomFieldValue::factory()->create([
            'custom_field_id' => $field->id,
            'entity_type' => Member::class,
            'entity_id' => $member->id,
            'value_string' => 'PO-999',
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        expect($response->json('member.custom_fields.po_reference'))->toBe('PO-999');
    });

    it('returns empty custom_fields object when no values exist', function () {
        $member = Member::factory()->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        expect($response->json('member.custom_fields'))->toBe([]);
    });

    it('still works with explicit include=customFieldValues', function () {
        $member = Member::factory()->create();
        $field = CustomField::factory()->create([
            'name' => 'ref_code',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);
        CustomFieldValue::factory()->create([
            'custom_field_id' => $field->id,
            'entity_type' => Member::class,
            'entity_id' => $member->id,
            'value_string' => 'REF-123',
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}?include=customFieldValues")
            ->assertOk();

        expect($response->json('member.custom_fields.ref_code'))->toBe('REF-123');
    });

    it('keeps other includes opt-in only', function () {
        $member = Member::factory()->create();
        Address::factory()->for($member, 'addressable')->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        expect($response->json('member.addresses'))->toBe([])
            ->and($response->json('member.custom_fields'))->toBeArray();
    });
});

describe('CRMS schema compatibility — single member', function () {
    it('matches CRMS Organisation member response shape', function () {
        $saleTaxClass = OrganisationTaxClass::factory()->create(['name' => 'Default']);
        $purchaseTaxClass = OrganisationTaxClass::factory()->create(['name' => 'Default']);
        $owner = Member::factory()->user()->create(['name' => 'Ben Bowles']);
        $country = Country::factory()->create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'currency_code' => 'GBP',
        ]);

        $member = Member::factory()->organisation()->create([
            'name' => 'Acme Events Ltd',
            'description' => 'Event production company',
            'is_active' => false,
            'bookable' => false,
            'location_type' => 1,
            'locale' => 'en-GB',
            'day_cost' => 0,
            'hour_cost' => 0,
            'distance_cost' => 0,
            'flat_rate_cost' => 0,
            'sale_tax_class_id' => $saleTaxClass->id,
            'purchase_tax_class_id' => $purchaseTaxClass->id,
            'tag_list' => ['Review Documents'],
            'mapping_id' => 'ce1b9fde-b19f-4b9c-bd00-30a448c757b8',
            'account_number' => 'ACC-001',
            'tax_number' => 'GB123456789',
            'is_cash' => false,
            'is_on_stop' => true,
            'rating' => 0,
            'owned_by' => $owner->id,
            'discount_category_id' => 5,
            'invoice_term_length' => 0,
        ]);

        Address::factory()->for($member, 'addressable')->create([
            'name' => 'Acme Events Ltd',
            'street' => '123 Main St',
            'city' => 'London',
            'county' => 'Greater London',
            'postcode' => 'EC1A 1BB',
            'country_id' => $country->id,
            'is_primary' => true,
        ]);

        $contact = Member::factory()->contact()->create(['name' => 'Freddie Meunier']);
        MemberRelationship::factory()->create([
            'member_id' => $contact->id,
            'related_member_id' => $member->id,
        ]);

        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}?include=addresses,emails,phones,links,contacts")
            ->assertOk();

        $data = $response->json('member');

        // Core CRMS fields
        expect($data)->toHaveKeys([
            'id', 'name', 'description', 'active', 'bookable', 'location_type',
            'locale', 'day_cost', 'hour_cost', 'distance_cost', 'flat_rate_cost',
            'membership_id', 'membership_type',
            'lawful_basis_type_id', 'lawful_basis_type_name',
            'sale_tax_class_id', 'sale_tax_class_name',
            'purchase_tax_class_id', 'purchase_tax_class_name',
            'tag_list', 'custom_fields', 'icon_exists?', 'mapping_id',
            'created_at', 'updated_at',
            'membership', 'primary_address', 'icon', 'identity',
            'emails', 'phones', 'links', 'addresses',
            'service_stock_levels', 'child_members', 'parent_members',
        ]);

        // deleted_at should NOT be in response (CRMS doesn't expose it)
        expect($data)->not->toHaveKey('deleted_at');

        // Field types match CRMS
        expect($data['id'])->toBeInt()
            ->and($data['name'])->toBeString()
            ->and($data['description'])->toBeString()
            ->and($data['active'])->toBeBool()
            ->and($data['bookable'])->toBeBool()
            ->and($data['location_type'])->toBeInt()
            ->and($data['locale'])->toBeString()
            ->and($data['membership_type'])->toBe('Organisation');

        // Money fields are decimal strings (CRMS format)
        expect($data['day_cost'])->toBe('0.00')
            ->and($data['hour_cost'])->toBe('0.00')
            ->and($data['distance_cost'])->toBe('0.00')
            ->and($data['flat_rate_cost'])->toBe('0.00');

        // Tax class names resolved
        expect($data['sale_tax_class_id'])->toBe($saleTaxClass->id)
            ->and($data['sale_tax_class_name'])->toBe('Default')
            ->and($data['purchase_tax_class_id'])->toBe($purchaseTaxClass->id)
            ->and($data['purchase_tax_class_name'])->toBe('Default');

        // Tags as array
        expect($data['tag_list'])->toBe(['Review Documents']);

        // Custom fields as object
        expect($data['custom_fields'])->toBeArray();

        // membership_id (same as id since we store on members table)
        expect($data['membership_id'])->toBeInt();

        // icon_exists derived from icon_url
        expect($data['icon_exists?'])->toBeFalse();

        // icon and identity objects
        expect($data['icon'])->toBeNull();
        expect($data['identity'])->toBeNull();

        // Mapping ID
        expect($data['mapping_id'])->toBe('ce1b9fde-b19f-4b9c-bd00-30a448c757b8');

        // CRMS date format: Z suffix with milliseconds (e.g. 2018-07-11T11:54:17.541Z)
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
        expect($data['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');

        // Organisation membership object (CRMS shape)
        expect($data['membership'])->toHaveKeys([
            'id', 'number', 'tax_class_id', 'cash', 'on_stop', 'rating',
            'owned_by', 'price_category_id', 'discount_category_id',
            'tax_number', 'peppol_id', 'chamber_of_commerce_number',
            'global_location_number', 'invoice_term', 'invoice_term_length',
        ]);
        expect($data['membership']['cash'])->toBeFalse()
            ->and($data['membership']['on_stop'])->toBeTrue()
            ->and($data['membership']['rating'])->toBe(0)
            ->and($data['membership']['owned_by'])->toBe($owner->id)
            ->and($data['membership']['discount_category_id'])->toBe(5)
            ->and($data['membership']['tax_class_id'])->toBe($saleTaxClass->id)
            ->and($data['membership']['number'])->toBe('ACC-001')
            ->and($data['membership']['tax_number'])->toBe('GB123456789');

        // Primary address object
        expect($data['primary_address'])->not->toBeNull();
        expect($data['primary_address'])->toHaveKeys([
            'id', 'name', 'street', 'city', 'county', 'postcode', 'country_id', 'is_primary',
        ]);
        expect($data['primary_address']['city'])->toBe('London')
            ->and($data['primary_address']['is_primary'])->toBeTrue();

        // Nested arrays present (empty arrays, not null — CRMS compat)
        expect($data['emails'])->toBeArray()
            ->and($data['phones'])->toBeArray()
            ->and($data['links'])->toBeArray()
            ->and($data['addresses'])->toBeArray()
            ->and($data['service_stock_levels'])->toBeArray();

        // child_members (linked contacts for this org)
        expect($data['child_members'])->toBeArray()->toHaveCount(1);
        expect($data['child_members'][0])->toHaveKeys([
            'id', 'relatable_id', 'relatable_type', 'relatable_name',
            'relatable_membership_type', 'related_id', 'related_type',
            'related_name', 'related_membership_type',
        ]);
        expect($data['child_members'][0]['related_name'])->toBe('Freddie Meunier')
            ->and($data['child_members'][0]['related_membership_type'])->toBe('Contact')
            ->and($data['child_members'][0]['relatable_name'])->toBe('Acme Events Ltd')
            ->and($data['child_members'][0]['relatable_membership_type'])->toBe('Organisation');

        // parent_members (empty for orgs)
        expect($data['parent_members'])->toBeArray()->toBeEmpty();
    });

    it('matches CRMS Contact member response shape with contact membership', function () {
        $member = Member::factory()->contact()->create([
            'name' => 'John Smith',
            'title' => 'Mr',
            'department' => 'Sales',
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        $data = $response->json('member');

        // Contact membership object
        expect($data['membership'])->toHaveKeys(['id', 'title', 'department']);
        expect($data['membership']['title'])->toBe('Mr')
            ->and($data['membership']['department'])->toBe('Sales');
    });

    it('returns parent_members for a Contact with linked organisations', function () {
        $org = Member::factory()->organisation()->create(['name' => 'Parent Org']);
        $contact = Member::factory()->contact()->create(['name' => 'Child Contact']);
        MemberRelationship::factory()->create([
            'member_id' => $contact->id,
            'related_member_id' => $org->id,
        ]);

        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$contact->id}?include=organisations")
            ->assertOk();

        $data = $response->json('member');
        expect($data['parent_members'])->toBeArray()->toHaveCount(1);
        expect($data['parent_members'][0]['related_name'])->toBe('Parent Org')
            ->and($data['parent_members'][0]['related_membership_type'])->toBe('Organisation')
            ->and($data['parent_members'][0]['relatable_name'])->toBe('Child Contact');
        expect($data['child_members'])->toBeArray()->toBeEmpty();
    });

    it('returns icon object when icon_url is set', function () {
        $member = Member::factory()->create([
            'icon_url' => 'https://example.com/icon.png',
            'icon_thumb_url' => 'https://example.com/icon_thumb.png',
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        expect($response->json('member.icon_exists?'))->toBeTrue();
        expect($response->json('member.icon'))->toBe([
            'url' => 'https://example.com/icon.png',
            'thumb_url' => 'https://example.com/icon_thumb.png',
        ]);
    });

    it('returns minimal membership for User type members', function () {
        $member = Member::factory()->user()->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        $data = $response->json('member');
        expect($data['membership'])->toBe(['id' => $member->id])
            ->and($data['membership_type'])->toBe('User');
    });

    it('separates primary address from other addresses', function () {
        $member = Member::factory()->create();
        $country = Country::factory()->create();
        Address::factory()->for($member, 'addressable')->create([
            'is_primary' => true, 'city' => 'London', 'country_id' => $country->id,
        ]);
        Address::factory()->for($member, 'addressable')->create([
            'is_primary' => false, 'city' => 'Manchester', 'country_id' => $country->id,
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}?include=addresses")
            ->assertOk();

        $data = $response->json('member');
        expect($data['primary_address'])->not->toBeNull()
            ->and($data['primary_address']['city'])->toBe('London');
        expect($data['addresses'])->toHaveCount(1)
            ->and($data['addresses'][0]['city'])->toBe('Manchester');
    });

    it('returns resource cost values as decimal strings', function () {
        $member = Member::factory()->create([
            'day_cost' => 10000,
            'hour_cost' => 1500,
            'distance_cost' => 100,
            'flat_rate_cost' => 30000,
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/members/{$member->id}")
            ->assertOk();

        expect($response->json('member.day_cost'))->toBe('100.00')
            ->and($response->json('member.hour_cost'))->toBe('15.00')
            ->and($response->json('member.distance_cost'))->toBe('1.00')
            ->and($response->json('member.flat_rate_cost'))->toBe('300.00');
    });
});

describe('CRMS schema compatibility — member collection', function () {
    it('matches CRMS collection response shape with plural key and meta', function () {
        $saleTaxClass = OrganisationTaxClass::factory()->create(['name' => 'Standard']);
        Member::factory()->organisation()->create([
            'name' => 'Org A',
            'sale_tax_class_id' => $saleTaxClass->id,
            'bookable' => false,
            'location_type' => 1,
        ]);
        Member::factory()->contact()->create([
            'name' => 'Contact B',
            'title' => 'Mrs',
            'department' => 'Finance',
        ]);
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members')
            ->assertOk();

        // Collection uses plural key
        $response->assertJsonStructure([
            'members' => [
                '*' => [
                    'id', 'name', 'description', 'active', 'bookable', 'location_type',
                    'locale', 'day_cost', 'hour_cost', 'distance_cost', 'flat_rate_cost',
                    'membership_type', 'lawful_basis_type_id',
                    'sale_tax_class_id', 'purchase_tax_class_id',
                    'tag_list', 'custom_fields', 'mapping_id',
                    'created_at', 'updated_at', 'membership',
                ],
            ],
            'meta' => ['total', 'per_page', 'page'],
        ]);

        // Verify each member has correct membership shape for its type
        $members = $response->json('members');
        $org = collect($members)->firstWhere('membership_type', 'Organisation');
        $contact = collect($members)->firstWhere('membership_type', 'Contact');

        expect($org['membership'])->toHaveKeys([
            'id', 'number', 'tax_class_id', 'cash', 'on_stop', 'rating',
            'owned_by', 'price_category_id', 'discount_category_id',
            'tax_number', 'peppol_id', 'chamber_of_commerce_number',
            'global_location_number', 'invoice_term', 'invoice_term_length',
        ]);

        expect($contact['membership'])->toHaveKeys(['id', 'title', 'department']);
        expect($contact['membership']['title'])->toBe('Mrs');

        // Money fields are decimal strings in collection too
        expect($org['day_cost'])->toBe('0.00');
        expect($org['location_type'])->toBe(1);
    });

    it('returns meta with pagination matching CRMS format', function () {
        Member::factory()->count(25)->create();
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $totalMembers = \App\Models\Member::count();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?page=2&per_page=10')
            ->assertOk();

        $meta = $response->json('meta');
        expect($meta)->toHaveKeys(['total', 'per_page', 'page'])
            ->and($meta['total'])->toBe($totalMembers)
            ->and($meta['per_page'])->toBe(10)
            ->and($meta['page'])->toBe(2);

        expect($response->json('members'))->toHaveCount(10);
    });
});
