<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\MembershipType;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use HasAttachments, HasCustomFields, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'membership_type',
        'is_active',
        'description',
        'locale',
        'default_currency_code',
        'bookable',
        'location_type',
        'day_cost',
        'hour_cost',
        'distance_cost',
        'flat_rate_cost',
        'lawful_basis_type_id',
        'sale_tax_class_id',
        'purchase_tax_class_id',
        'tag_list',
        'icon_url',
        'icon_thumb_url',
        'mapping_id',
        // Organisation membership fields
        'account_number',
        'tax_number',
        'peppol_id',
        'chamber_of_commerce_number',
        'global_location_number',
        'is_cash',
        'is_on_stop',
        'rating',
        'owned_by',
        'price_category_id',
        'discount_category_id',
        'invoice_term_id',
        'invoice_term_length',
        // Contact membership fields
        'title',
        'department',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'membership_type' => MembershipType::class,
            'is_active' => 'boolean',
            'bookable' => 'boolean',
            'location_type' => 'integer',
            'day_cost' => 'integer',
            'hour_cost' => 'integer',
            'distance_cost' => 'integer',
            'flat_rate_cost' => 'integer',
            'is_cash' => 'boolean',
            'is_on_stop' => 'boolean',
            'rating' => 'integer',
            'invoice_term_length' => 'integer',
            'tag_list' => 'array',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->enum('membership_type')->label('Type')->filterable()->sortable()->groupable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->text('description')->label('Description')->searchable();
        $builder->string('locale')->label('Locale')->filterable();
        $builder->string('default_currency_code')->label('Default Currency')->filterable();
        $builder->boolean('bookable')->label('Bookable')->filterable()->sortable();
        $builder->relation('sale_tax_class_id')->label('Sale Tax Class')
            ->relation('saleTaxClass', 'belongsTo', OrganisationTaxClass::class, 'name')
            ->filterable();
        $builder->relation('purchase_tax_class_id')->label('Purchase Tax Class')
            ->relation('purchaseTaxClass', 'belongsTo', OrganisationTaxClass::class, 'name')
            ->filterable();
        $builder->json('tag_list')->label('Tags')->searchable();
        $builder->string('account_number')->label('Account Number')->searchable()->filterable();
        $builder->boolean('is_on_stop')->label('On Stop')->filterable()->sortable();
        $builder->relation('owned_by')->label('Owner')
            ->relation('owner', 'belongsTo', self::class, 'name')
            ->filterable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * @return MorphMany<Email, $this>
     */
    public function emails(): MorphMany
    {
        return $this->morphMany(Email::class, 'emailable');
    }

    /**
     * @return MorphMany<Phone, $this>
     */
    public function phones(): MorphMany
    {
        return $this->morphMany(Phone::class, 'phoneable');
    }

    /**
     * @return MorphMany<Link, $this>
     */
    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable');
    }

    /**
     * @return BelongsTo<OrganisationTaxClass, $this>
     */
    public function saleTaxClass(): BelongsTo
    {
        return $this->belongsTo(OrganisationTaxClass::class, 'sale_tax_class_id');
    }

    /**
     * @return BelongsTo<OrganisationTaxClass, $this>
     */
    public function purchaseTaxClass(): BelongsTo
    {
        return $this->belongsTo(OrganisationTaxClass::class, 'purchase_tax_class_id');
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function lawfulBasisType(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'lawful_basis_type_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owned_by');
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function invoiceTerm(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'invoice_term_id');
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Direct member relationship records for this member.
     *
     * @return HasMany<MemberRelationship, $this>
     */
    public function memberRelationships(): HasMany
    {
        return $this->hasMany(MemberRelationship::class);
    }

    /**
     * Organisations this contact belongs to (via member_relationships).
     *
     * @return BelongsToMany<Member, $this>
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_relationships', 'member_id', 'related_member_id')
            ->withPivot('relationship_type', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Contacts that belong to this organisation (inverse of organisations).
     *
     * @return BelongsToMany<Member, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_relationships', 'related_member_id', 'member_id')
            ->withPivot('relationship_type', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Scope to members of a given type.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeOfType(Builder $query, MembershipType $type): Builder
    {
        return $query->where('membership_type', $type);
    }

    /**
     * Scope to active members.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to archived (soft-deleted) members only.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include archived (soft-deleted) members.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withTrashed();
    }

    /**
     * Scope to contacts that belong to a given organisation member.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeContactsOf(Builder $query, int $memberId): Builder
    {
        return $query->whereIn('id', function ($sub) use ($memberId): void {
            $sub->select('member_id')
                ->from('member_relationships')
                ->where('related_member_id', $memberId);
        });
    }

    /**
     * Scope to organisations that a given contact member belongs to.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeOrganisationsOf(Builder $query, int $memberId): Builder
    {
        return $query->whereIn('id', function ($sub) use ($memberId): void {
            $sub->select('related_member_id')
                ->from('member_relationships')
                ->where('member_id', $memberId);
        });
    }

    /**
     * Format a money value from minor units to decimal string for API responses.
     */
    public function formatMoneyCost(string $attribute): string
    {
        $value = (int) $this->getAttribute($attribute);

        return number_format($value / 100, 2, '.', '');
    }
}
