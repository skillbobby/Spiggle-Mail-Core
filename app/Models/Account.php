<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => \App\Casts\EncryptedOrPlainArray::class,
            'paused_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
