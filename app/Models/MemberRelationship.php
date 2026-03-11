<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRelationship extends Model
{
    /** @use HasFactory<\Database\Factories\MemberRelationshipFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'related_member_id',
        'relationship_type',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * The contact member in this relationship.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The organisation member in this relationship.
     *
     * @return BelongsTo<Member, $this>
     */
    public function relatedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }
}
