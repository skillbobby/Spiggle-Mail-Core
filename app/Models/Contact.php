<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'open_count' => 'integer',
            'click_count' => 'integer',
            'ignored_issues_count' => 'integer',
            'last_sent_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'last_chance_at' => 'datetime',
            'last_engaged_at' => 'datetime',
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

    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDormant(): bool
    {
        return $this->status === 'dormant';
    }

    public function ensureConfirmationToken(): string
    {
        if (!$this->confirmation_token) {
            $this->confirmation_token = Str::random(48);
            $this->save();
        }
        return $this->confirmation_token;
    }

    public function ensureKeepToken(): string
    {
        if (!$this->keep_token) {
            $this->keep_token = Str::random(48);
            $this->save();
        }
        return $this->keep_token;
    }

    public function recordEngagement(string $type = 'open'): void
    {
        $this->ignored_issues_count = 0;
        $this->last_engaged_at = now();
        if ($type === 'open') {
            $this->increment('open_count');
            $this->last_opened_at = now();
        } elseif ($type === 'click') {
            $this->increment('click_count');
            $this->last_opened_at = now();
        }

        // Restore if dormant
        if ($this->status === 'dormant') {
            $this->status = 'subscribed';
        }

        $this->save();
    }
}
