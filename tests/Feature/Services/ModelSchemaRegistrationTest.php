<?php

use App\Models\ActionLog;
use App\Models\Address;
use App\Models\Attachment;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomView;
use App\Models\Email;
use App\Models\ExchangeRate;
use App\Models\Link;
use App\Models\Phone;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Webhook;
use App\Services\SchemaRegistry;

describe('Phase 1 Model Schema Registration', function () {
    it('resolves Address schema', function () {
        $schema = (new SchemaRegistry)->resolve(Address::class);
        expect($schema)->toHaveKeys(['name', 'street', 'city', 'postcode', 'country_id', 'type_id', 'is_primary', 'latitude', 'longitude']);
        expect($schema['city']->filterable)->toBeTrue();
        expect($schema['is_primary']->type)->toBe('boolean');
    });

    it('resolves Country schema', function () {
        $schema = (new SchemaRegistry)->resolve(Country::class);
        expect($schema)->toHaveKeys(['code', 'code3', 'name', 'currency_code', 'is_active']);
        expect($schema['code']->required)->toBeTrue();
        expect($schema['is_active']->groupable)->toBeTrue();
    });

    it('resolves Currency schema', function () {
        $schema = (new SchemaRegistry)->resolve(Currency::class);
        expect($schema)->toHaveKeys(['code', 'name', 'symbol', 'decimal_places', 'is_enabled']);
        expect($schema['is_enabled']->type)->toBe('boolean');
    });

    it('resolves ExchangeRate schema', function () {
        $schema = (new SchemaRegistry)->resolve(ExchangeRate::class);
        expect($schema)->toHaveKeys(['source_currency_code', 'target_currency_code', 'rate', 'effective_at', 'expires_at']);
        expect($schema['rate']->type)->toBe('decimal');
    });

    it('resolves Email schema', function () {
        $schema = (new SchemaRegistry)->resolve(Email::class);
        expect($schema)->toHaveKeys(['address', 'type_id', 'is_primary']);
        expect($schema['address']->searchable)->toBeTrue();
    });

    it('resolves Phone schema', function () {
        $schema = (new SchemaRegistry)->resolve(Phone::class);
        expect($schema)->toHaveKeys(['number', 'country_code', 'type_id', 'is_primary']);
        expect($schema['number']->searchable)->toBeTrue();
    });

    it('resolves Link schema', function () {
        $schema = (new SchemaRegistry)->resolve(Link::class);
        expect($schema)->toHaveKeys(['url', 'name', 'type_id']);
        expect($schema['url']->required)->toBeTrue();
    });

    it('resolves Attachment schema', function () {
        $schema = (new SchemaRegistry)->resolve(Attachment::class);
        expect($schema)->toHaveKeys(['uuid', 'original_name', 'mime_type', 'file_size', 'category', 'scan_status', 'uploaded_by']);
        expect($schema['file_size']->aggregatable)->toBeTrue();
        expect($schema['file_size']->aggregateFunctions)->toContain('sum');
    });

    it('resolves User schema', function () {
        $schema = (new SchemaRegistry)->resolve(User::class);
        expect($schema)->toHaveKeys(['name', 'email', 'is_owner', 'is_admin', 'is_active', 'timezone', 'member_id', 'last_login_at']);
        expect($schema['member_id']->relationType)->toBe('belongsTo');
    });

    it('resolves ActionLog schema', function () {
        $schema = (new SchemaRegistry)->resolve(ActionLog::class);
        expect($schema)->toHaveKeys(['action', 'auditable_type', 'auditable_id', 'user_id', 'ip_address', 'created_at']);
        expect($schema['action']->groupable)->toBeTrue();
    });

    it('resolves Webhook schema', function () {
        $schema = (new SchemaRegistry)->resolve(Webhook::class);
        expect($schema)->toHaveKeys(['url', 'events', 'is_active', 'consecutive_failures', 'disabled_at', 'user_id']);
        expect($schema['url']->searchable)->toBeTrue();
    });

    it('resolves CustomView schema', function () {
        $schema = (new SchemaRegistry)->resolve(CustomView::class);
        expect($schema)->toHaveKeys(['name', 'entity_type', 'visibility', 'is_default', 'user_id']);
        expect($schema['entity_type']->groupable)->toBeTrue();
    });

    it('resolves TaxRate schema', function () {
        $schema = (new SchemaRegistry)->resolve(TaxRate::class);
        expect($schema)->toHaveKeys(['name', 'description', 'rate', 'is_active']);
        expect($schema['rate']->type)->toBe('decimal');
    });

    it('resolves TaxRule schema', function () {
        $schema = (new SchemaRegistry)->resolve(TaxRule::class);
        expect($schema)->toHaveKeys(['organisation_tax_class_id', 'product_tax_class_id', 'tax_rate_id', 'priority', 'is_active']);
        expect($schema['tax_rate_id']->relationType)->toBe('belongsTo');
    });
});
