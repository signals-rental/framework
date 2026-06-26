<?php

namespace App\Models;

use Database\Factories\OpportunitySectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A custom line-item grouping ("section") on an opportunity — a plain,
 * NON-event-sourced row used by the line-item editor to group lines under
 * operator-named headings (M8-3 grouping decision).
 *
 * Sections are deliberately decoupled from the Verbs event stream: they are plain
 * rows written only by plain actions and never by a Verbs event/apply()/handle(),
 * so a Verbs replay rebuilds the `opportunity_items` projection without disturbing
 * section rows. Under the unified line-item model the line -> group membership lives
 * on the `opportunity_items` materialised tree itself (the legacy
 * `opportunity_items.section_id` link was dropped at the unified-line-items cutover).
 *
 * Sections may also nest: a section can carry a `parent_id` pointing at another
 * section on the same opportunity, giving the editor sub-groups. The hierarchy is
 * a plain column too, so a Verbs replay never disturbs it.
 *
 * Under the unified group model every group is a real section. Auto-created groups
 * (product-category buckets) carry an `auto_group_key` (e.g. "auto:42") that marks
 * them as auto groups and is the find-or-create key on the add path; user-created
 * sections leave it NULL. Auto and user sections are otherwise identical — both are
 * draggable, nestable, renamable and deletable.
 *
 * @property int $id
 * @property int $opportunity_id
 * @property int|null $parent_id
 * @property string|null $auto_group_key
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpportunitySection extends Model
{
    /** @use HasFactory<OpportunitySectionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'opportunity_id',
        'parent_id',
        'auto_group_key',
        'name',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * The parent section this section nests under (null when top-level).
     *
     * @return BelongsTo<OpportunitySection, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OpportunitySection::class, 'parent_id');
    }

    /**
     * Child (nested) sections, in display order.
     *
     * @return HasMany<OpportunitySection, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(OpportunitySection::class, 'parent_id')->orderBy('sort_order');
    }
}
