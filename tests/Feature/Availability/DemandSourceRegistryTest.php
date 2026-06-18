<?php

use App\Contracts\DemandResolverContract;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\DemandSourceDefinition;
use App\Services\DemandSourceRegistry;

it('registers the core opportunity_item source by default', function () {
    $registry = app(DemandSourceRegistry::class);

    expect($registry->has('opportunity_item'))->toBeTrue();

    $definition = $registry->get('opportunity_item');

    expect($definition)->toBeInstanceOf(DemandSourceDefinition::class)
        ->and($definition->type)->toBe('opportunity_item')
        ->and($definition->displayName)->toBe('Bookings')
        ->and($definition->resolverClass)->toBe(OpportunityItemDemandResolver::class);
});

it('registers and retrieves additional sources', function () {
    $registry = new DemandSourceRegistry;

    $registry->register(new DemandSourceDefinition(
        type: 'quarantine',
        displayName: 'Quarantine',
        resolverClass: OpportunityItemDemandResolver::class,
        colour: '#EF4444',
        icon: 'shield-exclamation',
    ));

    expect($registry->has('quarantine'))->toBeTrue()
        ->and($registry->all())->toHaveKey('quarantine')
        ->and($registry->all())->toHaveCount(1);
});

it('throws for an unknown source type', function () {
    $registry = new DemandSourceRegistry;

    $registry->get('does-not-exist');
})->throws(InvalidArgumentException::class, 'Unknown demand source: does-not-exist');

it('resolves a source resolver from the container', function () {
    $resolver = app(DemandSourceRegistry::class)->resolve('opportunity_item');

    expect($resolver)->toBeInstanceOf(DemandResolverContract::class)
        ->and($resolver)->toBeInstanceOf(OpportunityItemDemandResolver::class)
        ->and($resolver->sourceType())->toBe('opportunity_item');
});
