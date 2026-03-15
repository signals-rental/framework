<?php

use App\Enums\FeatureProfile;

$allModuleKeys = [
    'products',
    'services',
    'stock',
    'serialisation',
    'opportunities',
    'invoicing',
    'credit_notes',
    'purchase_orders',
    'projects',
    'crm',
    'inspections',
    'vehicles',
    'quarantines',
    'discussions',
    'webhooks',
    'crew',
];

it('returns all 16 module keys for every profile', function () use ($allModuleKeys) {
    foreach (FeatureProfile::cases() as $profile) {
        $modules = $profile->modules();
        expect(array_keys($modules))->toBe($allModuleKeys, "Profile {$profile->value} missing expected module keys");
    }
});

it('has correct DryHire module mapping', function () {
    $modules = FeatureProfile::DryHire->modules();

    expect($modules)->toBe([
        'products' => true,
        'services' => false,
        'stock' => true,
        'serialisation' => false,
        'opportunities' => true,
        'invoicing' => true,
        'credit_notes' => false,
        'purchase_orders' => false,
        'projects' => false,
        'crm' => false,
        'inspections' => false,
        'vehicles' => false,
        'quarantines' => false,
        'discussions' => false,
        'webhooks' => false,
        'crew' => false,
    ]);
});

it('has all 16 modules enabled for FullService', function () {
    $modules = FeatureProfile::FullService->modules();

    expect($modules)->toHaveCount(16);

    foreach ($modules as $module => $enabled) {
        expect($enabled)->toBeTrue("FullService module '{$module}' should be true");
    }
});

it('has correct Crew module mapping', function () {
    $modules = FeatureProfile::Crew->modules();

    expect($modules)->toBe([
        'products' => false,
        'services' => true,
        'stock' => false,
        'serialisation' => false,
        'opportunities' => true,
        'invoicing' => true,
        'credit_notes' => false,
        'purchase_orders' => false,
        'projects' => true,
        'crm' => false,
        'inspections' => false,
        'vehicles' => false,
        'quarantines' => false,
        'discussions' => true,
        'webhooks' => false,
        'crew' => true,
    ]);
});

it('has correct Minimal module mapping', function () {
    $modules = FeatureProfile::Minimal->modules();

    expect($modules)->toBe([
        'products' => true,
        'services' => false,
        'stock' => false,
        'serialisation' => false,
        'opportunities' => true,
        'invoicing' => false,
        'credit_notes' => false,
        'purchase_orders' => false,
        'projects' => false,
        'crm' => false,
        'inspections' => false,
        'vehicles' => false,
        'quarantines' => false,
        'discussions' => false,
        'webhooks' => false,
        'crew' => false,
    ]);
});

it('has correct General module mapping', function () {
    $modules = FeatureProfile::General->modules();

    expect($modules)->toBe([
        'products' => true,
        'services' => false,
        'stock' => true,
        'serialisation' => false,
        'opportunities' => true,
        'invoicing' => true,
        'credit_notes' => false,
        'purchase_orders' => false,
        'projects' => false,
        'crm' => true,
        'inspections' => false,
        'vehicles' => false,
        'quarantines' => false,
        'discussions' => true,
        'webhooks' => false,
        'crew' => true,
    ]);
});
