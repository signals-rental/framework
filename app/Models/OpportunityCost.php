<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Database\Factories\OpportunityCostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of an event-sourced opportunity cost.
 *
 * Carries NO business logic — every mutation flows through a Verbs cost event
 * (M3-2: CostAdded / CostUpdated / CostRemoved) whose handle() method dual-writes
 * this row. It exists purely so list views, the API, and reports read costs with
 * zero event-sourcing penalty.
 *
 * The PK is application-assigned (allocated at event-fire time via
 * SequenceAllocator and baked into the genesis event), so Eloquent must not
 * auto-increment it. `amount` is INTEGER minor units; `quantity` is a genuine
 * decimal. Costs are NOT priced by the rate engine — they carry their own amount.
 *
 * @property int $id
 * @property int $state_id
 * @property int $opportunity_id
 * @property string $description
 * @property OpportunityCostType $cost_type
 * @property LineItemTransactionType $transaction_type
 * @property int $amount
 * @property string $quantity
 * @property string|null $tax_rate
 * @property string|null $currency_code
 * @property bool $is_optional
 * @property int $sort_order
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpportunityCost extends Model implements HasSchema
{
    /** @use HasFactory<OpportunityCostFactory> */
    use FormatsMoney, HasFactory;

    /**
     * The PK is application-assigned (allocated at event-fire time and baked into
     * the CostAdded event), so Eloquent must not auto-increment it.
     */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'opportunity_id',
        'description',
        'cost_type',
        'transaction_type',
        'amount',
        'quantity',
        'tax_rate',
        'currency_code',
        'is_optional',
        'sort_order',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_type' => OpportunityCostType::class,
            'transaction_type' => LineItemTransactionType::class,
            'amount' => 'integer',
            'quantity' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_optional' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('opportunity_id')->label('Opportunity')
            ->relation('opportunity', 'belongsTo', Opportunity::class, 'subject')
            ->filterable();
        $builder->string('description')->label('Description')->required()->searchable()->filterable()->sortable();
        $builder->enum('cost_type')->label('Cost Type')->filterable()->sortable()->groupable();
        $builder->enum('transaction_type')->label('Transaction Type')->filterable()->groupable();
        $builder->integer('amount')->label('Amount')->sortable();
        $builder->decimal('quantity')->label('Quantity')->filterable()->sortable();
        $builder->boolean('is_optional')->label('Optional')->filterable()->sortable();
        $builder->integer('sort_order')->label('Sort Order')->sortable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * Format money columns in the cost's own currency, falling back to the parent
     * opportunity's currency (when already loaded) and finally the company base
     * currency.
     */
    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        if (is_string($code) && $code !== '') {
            return $code;
        }

        $parentCode = $this->relationLoaded('opportunity') ? $this->opportunity?->currency_code : null;

        return is_string($parentCode) && $parentCode !== '' ? $parentCode : $this->baseFormattingCurrency();
    }
}
