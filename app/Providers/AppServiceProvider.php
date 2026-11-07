<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Account;
use App\Services\Email\AmazonSesEmailProvider;
use App\Services\Email\EmailProviderInterface;
use App\Services\Email\MockEmailProvider;
use App\Support\CurrentWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmailProviderInterface::class, function () {
            $driver = (string) config('services.ses.driver', 'mock');
            $region = (string) config('services.ses.region', 'us-east-1');
            $key = config('services.ses.key');
            $secret = config('services.ses.secret');
            $configSet = config('services.ses.configuration_set');

            // Allow operator-level SES settings in single-operator Core edition (or Super Admin override)
            try {
                if (! $this->app->runningUnitTests() && Schema::hasTable('accounts')) {
                    $account = CurrentWorkspace::account();
                    if (! $account && config('spiggle.edition') === 'core') {
                        $account = Account::first();
                    }

                    $accSettings = $account?->settings ?? [];
                    if (! empty($accSettings['ses_driver'])) {
                        $driver = (string) $accSettings['ses_driver'];
                    }
                    if (! empty($accSettings['aws_region'])) {
                        $region = (string) $accSettings['aws_region'];
                    }
                    if (! empty($accSettings['aws_access_key_id'])) {
                        $key = (string) $accSettings['aws_access_key_id'];
                    }
                    if (! empty($accSettings['aws_secret_access_key'])) {
                        $secret = (string) $accSettings['aws_secret_access_key'];
                    }
                    if (! empty($accSettings['ses_configuration_set'])) {
                        $configSet = (string) $accSettings['ses_configuration_set'];
                    }
                }
            } catch (Throwable) {
                // Fall back cleanly to config('services.ses.*')
            }

            if ($driver === 'live' || $driver === 'ses') {
                return new AmazonSesEmailProvider(
                    region: $region ?: 'us-east-1',
                    key: $key ? (string) $key : null,
                    secret: $secret ? (string) $secret : null,
                    configurationSet: $configSet ? (string) $configSet : null,
                );
            }

            return new MockEmailProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $identifier = $request->bearerToken()
                ? hash('sha256', $request->bearerToken())
                : ($request->ip() ?: 'anon');

            return Limit::perMinute(120)->by($identifier);
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(5)->by('otp-ip:'.($request->ip() ?: 'anon')),
                Limit::perMinutes(10, 5)->by('otp-email:'.($email ?: 'anon')),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by('otp-verify:'.($request->ip() ?: 'anon'));
        });

        RateLimiter::for('public-subscribe', function (Request $request) {
            return Limit::perMinute(10)->by('pub-sub:'.($request->ip() ?: 'anon'));
        });
    }
}
