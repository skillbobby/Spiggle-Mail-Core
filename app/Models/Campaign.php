<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'archive_pinned' => 'boolean',
            'archive_listed' => 'boolean',
            'web_views' => 'integer',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'open_count' => 'integer',
            'click_count' => 'integer',
            'bounce_count' => 'integer',
            'complaint_count' => 'integer',
        ];
    }

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
