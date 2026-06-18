<?php

namespace App\Models;

use App\Services\SequenceAllocator;
use Database\Factories\SequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Persisted counter backing {@see SequenceAllocator}.
 *
 * Each row is a named sequence whose `next_value` is the next integer the
 * allocator will hand out.
 */
class Sequence extends Model
{
    /** @use HasFactory<SequenceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'next_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }
}
