<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationType extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'category',
        'name',
        'description',
        'available_channels',
        'default_channels',
        'is_active',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_channels' => 'array',
            'default_channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasOne<NotificationSetting, $this>
     */
    public function setting(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Get the effective channels for this notification type (system override or defaults).
     *
     * @return list<string>
     */
    public function effectiveChannels(): array
    {
        $setting = $this->setting;

        if ($setting && ! $setting->is_enabled) {
            return [];
        }

        if ($setting && $setting->channels !== null) {
            /** @var list<string> */
            return $setting->channels;
        }

        /** @var list<string> */
        return $this->default_channels;
    }
}
