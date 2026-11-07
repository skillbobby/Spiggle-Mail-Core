<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Newsletter extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'double_opt_in' => 'boolean',
            'welcome_enabled' => 'boolean',
            'list_cleanup_enabled' => 'boolean',
            'cleanup_inactive_issues' => 'integer',
            'cleanup_inactive_days' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function getDefaultWelcomeSubject(): string
    {
        return "Welcome to {$this->name}";
    }

    public function getDefaultWelcomeMarkdown(): string
    {
        $desc = $this->description ? " {$this->description}" : '';

        return "Thanks for joining {$this->name}.{$desc}\n\nThe next issue lands in your inbox. Hit reply to say hi.";
    }

    public function getDefaultConfirmationSubject(): string
    {
        return "Confirm your subscription to {$this->name}";
    }

    public function getDefaultConfirmationMarkdown(): string
    {
        return "**One click and you're in.**\n\nYou asked to get {$this->name}. Confirm your email and the next issue lands in your inbox.\n\n<a href=\"{{confirm_url}}\" style=\"display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:8px;\">Confirm my subscription</a>\n\nDidn't sign up? Ignore this email and you won't hear from us again.";
    }
}
