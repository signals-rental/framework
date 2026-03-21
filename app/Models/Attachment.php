<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Attachment extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\AttachmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'original_name',
        'file_path',
        'thumb_path',
        'disk',
        'mime_type',
        'file_size',
        'category',
        'description',
        'scan_status',
        'scanned_at',
        'uploaded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('uuid')->label('UUID')->filterable();
        $builder->string('original_name')->label('File Name')->required()->searchable()->filterable()->sortable();
        $builder->string('mime_type')->label('MIME Type')->filterable()->groupable();
        $builder->integer('file_size')->label('File Size')->sortable()->aggregatable('sum', 'avg', 'max');
        $builder->string('category')->label('Category')->filterable()->groupable();
        $builder->text('description')->label('Description')->searchable();
        $builder->string('scan_status')->label('Scan Status')->filterable()->groupable();
        $builder->relation('uploaded_by')->label('Uploaded By')
            ->relation('uploadedBy', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    protected static function booted(): void
    {
        static::creating(function (Attachment $attachment): void {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get a signed temporary URL for the file.
     */
    public function url(int $expiry = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->file_path, now()->addMinutes($expiry));
    }

    /**
     * Get a signed temporary URL for the thumbnail.
     */
    public function thumbUrl(int $expiry = 60): ?string
    {
        if ($this->thumb_path === null) {
            return null;
        }

        return Storage::disk($this->disk)->temporaryUrl($this->thumb_path, now()->addMinutes($expiry));
    }
}
