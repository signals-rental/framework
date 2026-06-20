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
 * Sections are deliberately decoupled from the Verbs event stream: the
 * line -> section link lives on `opportunity_items.section_id`, which is written
 * only by plain actions and never by a Verbs event/apply()/handle(), so a Verbs
 * replay rebuilds the `opportunity_items` projection without disturbing section
 * assignments. Lines with no section fall back to automatic product-group
 * grouping in the UI.
 *
 * @property int $id
 * @property int $opportunity_id
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
        'name',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * Line items assigned to this section, in display order.
     *
     * @return HasMany<OpportunityItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OpportunityItem::class, 'section_id')->orderBy('sort_order');
    }
}
