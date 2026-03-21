<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\SchemaRegistry;
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
        'members' => \App\Models\Member::class,
        'stores' => \App\Models\Store::class,
        'addresses' => \App\Models\Address::class,
        'countries' => \App\Models\Country::class,
        'currencies' => \App\Models\Currency::class,
        'exchange_rates' => \App\Models\ExchangeRate::class,
        'emails' => \App\Models\Email::class,
        'phones' => \App\Models\Phone::class,
        'links' => \App\Models\Link::class,
        'attachments' => \App\Models\Attachment::class,
        'users' => \App\Models\User::class,
        'action_logs' => \App\Models\ActionLog::class,
        'webhooks' => \App\Models\Webhook::class,
        'custom_views' => \App\Models\CustomView::class,
        'tax_rates' => \App\Models\TaxRate::class,
        'tax_rules' => \App\Models\TaxRule::class,
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
    public function show(string $model, SchemaRegistry $registry): JsonResponse
    {
        $this->authorizeApi('schema.read', 'schema:read');

        $modelClass = self::MODEL_MAP[$model] ?? null;

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
            'model' => $model,
            'model_class' => Str::afterLast($modelClass, '\\'),
            'fields' => $schema,
        ]);
    }
}
