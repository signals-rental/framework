<?php

use App\Models\CustomField;
use App\Models\Member;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use App\Services\SchemaRegistry;
use App\ValueObjects\FieldDefinition;

describe('SchemaBuilder', function () {
    it('builds field definitions with correct types', function () {
        $builder = new SchemaBuilder;

        $builder->string('name');
        $builder->integer('count');
        $builder->boolean('is_active');
        $builder->datetime('created_at');
        $builder->currency('price');
        $builder->computed('full_name', 'string');

        $definitions = $builder->build();

        expect($definitions)
            ->toHaveCount(6)
            ->toHaveKeys(['name', 'count', 'is_active', 'created_at', 'price', 'full_name']);

        expect($definitions['name']->type)->toBe('string');
        expect($definitions['count']->type)->toBe('integer');
        expect($definitions['is_active']->type)->toBe('boolean');
        expect($definitions['created_at']->type)->toBe('datetime');
        expect($definitions['price']->type)->toBe('currency');
        expect($definitions['full_name']->type)->toBe('string');
        expect($definitions['full_name']->source)->toBe('computed');
    });

    it('creates all type-shortcut methods', function () {
        $builder = new SchemaBuilder;

        $builder->string('a');
        $builder->text('b');
        $builder->integer('c');
        $builder->decimal('d');
        $builder->boolean('e');
        $builder->date('f');
        $builder->datetime('g');
        $builder->currency('h');
        $builder->enum('i');
        $builder->json('j');
        $builder->relation('k');

        $definitions = $builder->build();

        expect($definitions['a']->type)->toBe('string');
        expect($definitions['b']->type)->toBe('text');
        expect($definitions['c']->type)->toBe('integer');
        expect($definitions['d']->type)->toBe('decimal');
        expect($definitions['e']->type)->toBe('boolean');
        expect($definitions['f']->type)->toBe('date');
        expect($definitions['g']->type)->toBe('datetime');
        expect($definitions['h']->type)->toBe('currency');
        expect($definitions['i']->type)->toBe('enum');
        expect($definitions['j']->type)->toBe('json');
        expect($definitions['k']->type)->toBe('relation');
    });
});

describe('FieldBuilder', function () {
    it('sets all properties via fluent API', function () {
        $builder = new FieldBuilder('total', 'currency');

        $definition = $builder
            ->label('Total Amount')
            ->description('The total')
            ->group('Financial')
            ->format('currency')
            ->alignment('right')
            ->widthHint(150)
            ->filterable()
            ->sortable()
            ->searchable()
            ->groupable()
            ->aggregatable('sum', 'avg')
            ->exportable()
            ->importable(false)
            ->required()
            ->nullable(false)
            ->rules(['numeric', 'min:0'])
            ->relation('currency', 'belongsTo', 'App\Models\Currency', 'code')
            ->crms('total_amount', 'cents_to_decimal')
            ->source('core')
            ->model('App\Models\Invoice')
            ->plugin('signals/invoicing')
            ->build();

        expect($definition)->toBeInstanceOf(FieldDefinition::class);
        expect($definition->name)->toBe('total');
        expect($definition->type)->toBe('currency');
        expect($definition->label)->toBe('Total Amount');
        expect($definition->description)->toBe('The total');
        expect($definition->group)->toBe('Financial');
        expect($definition->format)->toBe('currency');
        expect($definition->alignment)->toBe('right');
        expect($definition->widthHint)->toBe(150);
        expect($definition->filterable)->toBeTrue();
        expect($definition->sortable)->toBeTrue();
        expect($definition->searchable)->toBeTrue();
        expect($definition->groupable)->toBeTrue();
        expect($definition->aggregatable)->toBeTrue();
        expect($definition->aggregateFunctions)->toBe(['sum', 'avg']);
        expect($definition->exportable)->toBeTrue();
        expect($definition->importable)->toBeFalse();
        expect($definition->required)->toBeTrue();
        expect($definition->nullable)->toBeFalse();
        expect($definition->rules)->toBe(['numeric', 'min:0']);
        expect($definition->relationName)->toBe('currency');
        expect($definition->relationType)->toBe('belongsTo');
        expect($definition->relatedModel)->toBe('App\Models\Currency');
        expect($definition->relatedField)->toBe('code');
        expect($definition->crmsFieldName)->toBe('total_amount');
        expect($definition->crmsTransform)->toBe('cents_to_decimal');
        expect($definition->source)->toBe('core');
        expect($definition->model)->toBe('App\Models\Invoice');
        expect($definition->plugin)->toBe('signals/invoicing');
    });
});

describe('FieldDefinition', function () {
    it('toArray returns all properties', function () {
        $definition = new FieldDefinition(
            name: 'test_field',
            type: 'string',
            source: 'core',
            label: 'Test Field',
        );

        $array = $definition->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKeys([
            'name', 'type', 'source', 'model', 'plugin',
            'filterable', 'sortable', 'searchable', 'groupable', 'aggregatable',
            'exportable', 'importable',
            'label', 'description', 'group', 'format', 'alignment', 'widthHint',
            'rules', 'required', 'nullable',
            'relationName', 'relationType', 'relatedModel', 'relatedField',
            'crmsFieldName', 'crmsTransform', 'aggregateFunctions',
        ]);
        expect($array['name'])->toBe('test_field');
        expect($array['type'])->toBe('string');
        expect($array['source'])->toBe('core');
        expect($array['label'])->toBe('Test Field');
        expect($array['filterable'])->toBeTrue();
        expect($array['sortable'])->toBeTrue();
        expect($array['searchable'])->toBeFalse();
        expect($array['nullable'])->toBeTrue();
    });
});

describe('SchemaRegistry', function () {
    it('is registered as a singleton in the container', function () {
        $a = app(SchemaRegistry::class);
        $b = app(SchemaRegistry::class);

        expect($a)->toBe($b);
    });

    it('resolves Member schema with core fields', function () {
        $registry = new SchemaRegistry;
        $schema = $registry->resolve(Member::class);

        expect($schema)->toHaveKeys([
            'name', 'membership_type', 'is_active', 'description',
            'locale', 'default_currency_code', 'organisation_tax_class_id',
            'tag_list', 'created_at', 'updated_at',
        ]);

        expect($schema['name']->type)->toBe('string');
        expect($schema['name']->label)->toBe('Name');
        expect($schema['name']->required)->toBeTrue();
        expect($schema['name']->searchable)->toBeTrue();

        expect($schema['membership_type']->type)->toBe('enum');
        expect($schema['membership_type']->groupable)->toBeTrue();

        expect($schema['is_active']->type)->toBe('boolean');

        expect($schema['organisation_tax_class_id']->type)->toBe('relation');
        expect($schema['organisation_tax_class_id']->relationName)->toBe('organisationTaxClass');
        expect($schema['organisation_tax_class_id']->relationType)->toBe('belongsTo');
    });

    it('resolves Store schema with core fields', function () {
        $registry = new SchemaRegistry;
        $schema = $registry->resolve(Store::class);

        expect($schema)->toHaveKeys([
            'name', 'street', 'city', 'county', 'postcode',
            'country_code', 'country_id', 'phone', 'email',
            'is_default', 'created_at', 'updated_at',
        ]);

        expect($schema['name']->type)->toBe('string');
        expect($schema['name']->required)->toBeTrue();
        expect($schema['country_id']->relationType)->toBe('belongsTo');
        expect($schema['is_default']->type)->toBe('boolean');
    });

    it('merges custom fields into schema', function () {
        CustomField::factory()->forModule('Member')->create([
            'name' => 'po_reference',
            'display_name' => 'PO Reference',
            'is_searchable' => true,
            'is_required' => false,
        ]);

        $registry = new SchemaRegistry;
        $schema = $registry->resolve(Member::class);

        expect($schema)->toHaveKey('po_reference');
        expect($schema['po_reference']->source)->toBe('custom');
        expect($schema['po_reference']->type)->toBe('string');
        expect($schema['po_reference']->label)->toBe('PO Reference');
        expect($schema['po_reference']->searchable)->toBeTrue();
    });

    it('caches resolved schemas on second call', function () {
        $registry = new SchemaRegistry;

        $first = $registry->resolve(Member::class);
        $second = $registry->resolve(Member::class);

        expect($first)->toBe($second);
    });

    it('invalidates cache for a specific model', function () {
        $registry = new SchemaRegistry;
        $registry->resolve(Member::class);
        $registry->resolve(Store::class);

        $registry->invalidate(Member::class);

        // Member should be re-resolved (different array instance)
        // Store should still be cached
        $memberSchema = $registry->resolve(Member::class);
        expect($memberSchema)->toHaveKey('name');

        // Verify Store is still cached by checking identity
        $storeA = $registry->resolve(Store::class);
        $registry->invalidate(Member::class);
        $storeB = $registry->resolve(Store::class);
        expect($storeA)->toBe($storeB);
    });

    it('invalidates all cached schemas', function () {
        $registry = new SchemaRegistry;
        $first = $registry->resolve(Member::class);

        $registry->invalidateAll();

        $second = $registry->resolve(Member::class);
        expect($first)->not->toBe($second);
    });

    it('gives custom fields source=custom in merged schema', function () {
        CustomField::factory()->forModule('Store')->boolean()->create([
            'name' => 'has_loading_bay',
            'display_name' => 'Has Loading Bay',
        ]);

        $registry = new SchemaRegistry;
        $schema = $registry->resolve(Store::class);

        expect($schema['has_loading_bay']->source)->toBe('custom');
        expect($schema['has_loading_bay']->type)->toBe('boolean');

        // Core fields should still have source=core
        expect($schema['name']->source)->toBe('core');
    });

    it('returns only custom fields for models without HasSchema', function () {
        CustomField::factory()->forModule('Country')->create([
            'name' => 'region_code',
            'display_name' => 'Region Code',
        ]);

        $registry = new SchemaRegistry;
        $schema = $registry->resolve(\App\Models\Country::class);

        expect($schema)->toHaveKey('region_code');
        expect($schema['region_code']->source)->toBe('custom');
    });

    it('provides for() as alias for resolve()', function () {
        $registry = new SchemaRegistry;

        $resolved = $registry->resolve(Member::class);
        $forResult = $registry->for(Member::class);

        expect($resolved)->toBe($forResult);
    });
});
