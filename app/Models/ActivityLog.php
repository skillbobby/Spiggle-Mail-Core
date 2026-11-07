<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

class ActivityLog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'activity_logs';

    protected $fillable = [
        'id',
        'account_id',
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to quickly log an activity with contextual IP and user agent.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $action,
        string $description,
        ?string $accountId = null,
        array $properties = [],
        ?int $userId = null,
        ?Request $request = null
    ): self {
        $req = $request ?? RequestFacade::instance();

        if (! $accountId) {
            $user = $userId ? User::find($userId) : (auth()->user() ?? null);
            $accountId = $user?->account?->id ?? Account::first()?->id ?? '01MOCKACCOUNT0000000000000';
        }

        if (! $userId) {
            $userId = auth()->id() ?? session('authenticated_user_id');
        }

        return self::create([
            'account_id' => $accountId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $req?->ip(),
            'user_agent' => substr((string) ($req?->userAgent() ?? ''), 0, 500),
            'properties' => $properties,
        ]);
    }
}
