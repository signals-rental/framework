<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Database\Factories\OpportunityVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of an event-sourced quote version
 * (opportunity-lifecycle.md §8).
 *
 * Carries NO business logic — every mutation flows through a Verbs version event
 * whose handle() dual-writes this row. It exists purely so the API, list views,
 * and reports read versions with zero event-sourcing penalty.
 *
 * The PK is application-assigned (allocated at event-fire time via
 * SequenceAllocator and baked into the genesis event), so Eloquent must not
 * auto-increment it. Money columns are integer minor units (NET, ex-tax),
 * mirroring the `opportunities` totals shape.
 *
 * @property int $id
 * @property int $state_id
 * @property int $opportunity_id
 * @property int $version_number
 * @property int|null $parent_version_id
 * @property int|null $superseded_by_version_id
 * @property VersionType $version_type
 * @property string|null $label
 * @property bool $is_active
 * @property VersionStatus $status
 * @property string|null $decline_reason
 * @property int $charge_excluding_tax_total
 * @property int $tax_total
 * @property int $charge_including_tax_total
 * @property int $charge_total
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpportunityVersion extends Model implements HasSchema
{
    /** @use HasFactory<OpportunityVersionFactory> */
    use FormatsMoney, HasFactory;

    /**
     * The PK is application-assigned (allocated at event-fire time and baked into
     * the VersionCreated event), so Eloquent must not auto-increment it.
     */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'opportunity_id',
        'version_number',
        'parent_version_id',
        'superseded_by_version_id',
        'version_type',
        'label',
        'is_active',
        'status',
        'charge_excluding_tax_total',
        'tax_total',
        'charge_including_tax_total',
        'charge_total',
        'decline_reason',
        'notes',
        'created_by',
        'sent_at',
        'accepted_at',
        'declined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_type' => VersionType::class,
            'status' => VersionStatus::class,
            'is_active' => 'boolean',
            'charge_excluding_tax_total' => 'integer',
            'tax_total' => 'integer',
            'charge_including_tax_total' => 'integer',
            'charge_total' => 'integer',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->integer('version_number')->label('Version')->filterable()->sortable();
        $builder->string('label')->label('Label')->searchable()->filterable()->sortable();
        $builder->integer('version_type')->label('Type')->filterable()->sortable()->groupable();
        $builder->integer('status')->label('Status')->filterable()->sortable()->groupable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable();
        $builder->relation('opportunity_id')->label('Opportunity')
            ->relation('opportunity', 'belongsTo', Opportunity::class, 'subject')
            ->filterable();
        $builder->integer('charge_total')->label('Charge Total')->sortable();
        $builder->datetime('sent_at')->label('Sent')->sortable()->filterable();
        $builder->datetime('accepted_at')->label('Accepted')->sortable()->filterable();
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
     * The version this one superseded (revisions only; null for the first
     * version and for alternatives).
     *
     * @return BelongsTo<OpportunityVersion, $this>
     */
    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    /**
     * The author of this version.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Line items scoped to this version.
     *
     * @return HasMany<OpportunityItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OpportunityItem::class, 'version_id')->orderBy('sort_order');
    }
}
