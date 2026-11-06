<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Account;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Strictly resolves the authenticated user's own Account and Newsletter
 * without ever falling back to User::first(), Account::first(), or Newsletter::first().
 */
final class CurrentWorkspace
{
    public static function user(?Request $request = null): ?User
    {
        $request ??= request();

        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $userId = $request->hasSession() ? $request->session()->get('authenticated_user_id') : null;
        if ($userId) {
            return User::find($userId);
        }

        return null;
    }

    public static function account(?Request $request = null, bool $autoProvision = false): ?Account
    {
        $user = self::user($request);
        if (! $user) {
            return null;
        }

        $account = Account::where('user_id', $user->id)->first();
        if (! $account && $autoProvision) {
            $account = Account::create([
                'id' => (string) Str::ulid(),
                'user_id' => $user->id,
                'name' => ($user->name ?: explode('@', $user->email)[0]) . "'s Workspace",
                'status' => 'active',
            ]);
        }

        return $account;
    }

    public static function newsletter(?Request $request = null): ?Newsletter
    {
        $request ??= request();
        $account = self::account($request);
        if (! $account) {
            return null;
        }

        $selectedId = $request->hasSession() ? $request->session()->get('selected_newsletter_id') : null;
        if ($selectedId) {
            $selected = Newsletter::where('account_id', $account->id)->where('id', $selectedId)->first();
            if ($selected) {
                return $selected;
            }
        }

        return Newsletter::where('account_id', $account->id)->first();
    }
}
