<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\ActionLog;
use App\Models\Activity;
use App\Models\Address;
use App\Models\Attachment;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomView;
use App\Models\Email;
use App\Models\ExchangeRate;
use App\Models\Link;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\Phone;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\ShortageResolution;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Webhook;
use App\Services\SchemaRegistry;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SchemaController extends Controller
{
    /**
     * Map of URL-friendly model names to fully qualified class names.
     *
     * @var array<string, class-string>
     */
    private const MODEL_MAP = [
        'members' => Member::class,
        'stores' => Store::class,
        'addresses' => Address::class,
        'countries' => Country::class,
        'currencies' => Currency::class,
        'exchange_rates' => ExchangeRate::class,
        'emails' => Email::class,
        'phones' => Phone::class,
        'links' => Link::class,
        'attachments' => Attachment::class,
        'users' => User::class,
        'action_logs' => ActionLog::class,
        'webhooks' => Webhook::class,
        'custom_views' => CustomView::class,
        'tax_rates' => TaxRate::class,
        'tax_rules' => TaxRule::class,
        'products' => Product::class,
        'product_groups' => ProductGroup::class,
        'stock_levels' => StockLevel::class,
        'stock_transactions' => StockTransaction::class,
        'activities' => Activity::class,
        'rate_definitions' => RateDefinition::class,
        'product_rates' => ProductRate::class,
        'opportunities' => Opportunity::class,
        'opportunity_versions' => OpportunityVersion::class,
        'shortage_resolutions' => ShortageResolution::class,
    ];

    /**
     * List all available schema model names.
     */
    public function index(): JsonResponse
    {
        $this->authorizeApi('schema.read', 'schema:read');

        return response()->json([
            'schemas' => array_keys(self::MODEL_MAP),
        ]);
    }

    /**
     * Get the full field schema for a model.
     *
     * @param  string  $model  URL-friendly model name (e.g. 'members', 'stores')
     */
    #[ApiResponse(200, 'Model field schema', type: 'array{model: string, model_class: string, fields: array<string, array{name: string, label: string, type: string, required: bool, searchable: bool, filterable: bool, sortable: bool}>}')]
    #[ApiResponse(404, 'Unknown model', type: 'array{message: string, available: list<string>}')]
    public function show(string $model, SchemaRegistry $registry): JsonResponse
    {
        $this->authorizeApi('schema.read', 'schema:read');

        // Accept either the canonical plural key or its singular form.
        $key = isset(self::MODEL_MAP[$model]) ? $model : Str::plural($model);
        $modelClass = self::MODEL_MAP[$key] ?? null;

        if ($modelClass === null) {
            return response()->json([
                'message' => "Unknown schema model: {$model}",
                'available' => array_keys(self::MODEL_MAP),
            ], 404);
        }

        $fields = $registry->resolve($modelClass);

        $schema = [];
        foreach ($fields as $name => $definition) {
            $schema[$name] = $definition->toArray();
        }

        return response()->json([
            'model' => $key,
            'model_class' => Str::afterLast($modelClass, '\\'),
            'fields' => $schema,
        ]);
    }
}
