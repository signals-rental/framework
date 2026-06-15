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
use App\Jobs\DeliverWebhook;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);

    Webhook::factory()->create([
        'user_id' => $this->owner->id,
        'events' => [
            'tax_rate.created', 'tax_rate.updated', 'tax_rate.deleted',
            'tax_rule.created', 'tax_rule.updated', 'tax_rule.deleted',
        ],
        'is_active' => true,
    ]);
});

it('dispatches tax_rate.created webhook when creating a tax rate', function () {
    Queue::fake();

    $rate = (new CreateTaxRate)(CreateTaxRateData::from([
        'name' => 'New Rate',
        'rate' => '20.0000',
    ]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rate.created'
        && $job->payload['tax_rate']['id'] === $rate->id
        && $job->payload['tax_rate']['name'] === 'New Rate');
});

it('dispatches tax_rate.updated webhook when updating a tax rate', function () {
    Queue::fake();
    $rate = TaxRate::factory()->create();

    (new UpdateTaxRate)($rate, UpdateTaxRateData::from(['name' => 'Updated Rate']));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rate.updated'
        && $job->payload['tax_rate']['id'] === $rate->id);
});

it('dispatches tax_rate.deleted webhook when deleting a tax rate', function () {
    Queue::fake();
    $rate = TaxRate::factory()->create();
    $id = $rate->id;

    (new DeleteTaxRate)($rate);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rate.deleted'
        && $job->payload['id'] === $id);
});

it('dispatches tax_rule.created webhook when creating a tax rule', function () {
    Queue::fake();
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create();
    $rate = TaxRate::factory()->create();

    $rule = (new CreateTaxRule)(CreateTaxRuleData::from([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $rate->id,
    ]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rule.created'
        && $job->payload['tax_rule']['id'] === $rule->id);
});

it('dispatches tax_rule.updated webhook when updating a tax rule', function () {
    Queue::fake();
    $rule = TaxRule::factory()->create();

    (new UpdateTaxRule)($rule, UpdateTaxRuleData::from(['priority' => 5]));

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rule.updated'
        && $job->payload['tax_rule']['id'] === $rule->id);
});

it('dispatches tax_rule.deleted webhook when deleting a tax rule', function () {
    Queue::fake();
    $rule = TaxRule::factory()->create();
    $id = $rule->id;

    (new DeleteTaxRule)($rule);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'tax_rule.deleted'
        && $job->payload['id'] === $id);
});

it('does not dispatch tax webhooks when no active webhook exists', function () {
    Queue::fake();
    Webhook::query()->delete();

    (new CreateTaxRate)(CreateTaxRateData::from(['name' => 'Silent', 'rate' => '20.0000']));

    Queue::assertNotPushed(DeliverWebhook::class);
});
