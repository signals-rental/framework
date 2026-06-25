<?php

use App\Contracts\Opportunities\OpportunityLineItemsEditorContract;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;

/**
 * @param  Testable<Component>  $testable
 */
function lineItemsEditorInstance(Testable $testable): OpportunityLineItemsEditorContract
{
    $instance = $testable->instance();
    assert($instance instanceof OpportunityLineItemsEditorContract);

    return $instance;
}
