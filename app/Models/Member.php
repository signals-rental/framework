<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\MembershipType;
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
    use HasCustomFields, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'membership_type',
        'is_active',
        'description',
        'locale',
        'default_currency_code',
        'organisation_tax_class_id',
        'tag_list',
        'icon_url',
        'icon_thumb_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'membership_type' => MembershipType::class,
            'is_active' => 'boolean',
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
        $builder->relation('organisation_tax_class_id')->label('Tax Class')
            ->relation('organisationTaxClass', 'belongsTo', OrganisationTaxClass::class, 'name')
            ->filterable();
        $builder->json('tag_list')->label('Tags')->searchable();
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
    public function organisationTaxClass(): BelongsTo
    {
        return $this->belongsTo(OrganisationTaxClass::class);
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
}
