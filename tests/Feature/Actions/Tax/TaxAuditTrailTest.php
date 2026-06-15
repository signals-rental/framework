<?php

use App\Actions\Tax\CreateTaxRate;
use App\Actions\Tax\CreateTaxRule;
use App\Actions\Tax\DeleteTaxRate;
use App\Actions\Tax\DeleteTaxRule;
use App\Actions\Tax\UpdateTaxRate;
use App\Actions\Tax\UpdateTaxRule;
use App\Data\Tax\CreateTaxRateData;
use App\Data\Tax\CreateTaxRuleData;
use App\Data\Tax\UpdateTaxRateData;
use App\Data\Tax\UpdateTaxRuleData;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * End-to-end audit-trail coverage for tax actions.
 *
 * These intentionally do NOT fake AuditableEvent, so the auto-discovered
 * LogAction listener runs and writes an action_logs row. The queue is faked
 * only to suppress real webhook delivery.
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
    Queue::fake();
});

it('records an action_logs row when a tax rate is created', function () {
    $rate = (new CreateTaxRate)(CreateTaxRateData::from([
        'name' => 'Audited Rate',
        'rate' => '20.0000',
    ]));

    assertActionLogged('tax_rate.created', TaxRate::class, $rate->id, $this->owner->id);
});

it('records an action_logs row when a tax rate is updated', function () {
    $rate = TaxRate::factory()->create();

    (new UpdateTaxRate)($rate, UpdateTaxRateData::from(['name' => 'Renamed Rate']));

    assertActionLogged('tax_rate.updated', TaxRate::class, $rate->id, $this->owner->id);
});

it('records an action_logs row when a tax rate is deleted', function () {
    $rate = TaxRate::factory()->create();

    (new DeleteTaxRate)($rate);

    assertActionLogged('tax_rate.deleted', TaxRate::class, $rate->id, $this->owner->id);
});

it('records an action_logs row when a tax rule is created', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $rate = TaxRate::factory()->create();

    $rule = (new CreateTaxRule)(CreateTaxRuleData::from([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $rate->id,
    ]));

    assertActionLogged('tax_rule.created', TaxRule::class, $rule->id, $this->owner->id);
});

it('records an action_logs row when a tax rule is updated', function () {
    $rule = TaxRule::factory()->create();

    (new UpdateTaxRule)($rule, UpdateTaxRuleData::from(['priority' => 5]));

    assertActionLogged('tax_rule.updated', TaxRule::class, $rule->id, $this->owner->id);
});

it('records an action_logs row when a tax rule is deleted', function () {
    $rule = TaxRule::factory()->create();

    (new DeleteTaxRule)($rule);

    assertActionLogged('tax_rule.deleted', TaxRule::class, $rule->id, $this->owner->id);
});
