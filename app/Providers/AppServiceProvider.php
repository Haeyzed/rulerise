<?php

namespace App\Providers;

use Carbon\Carbon;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Dedoc\Scramble\Support\Generator\{OpenApi, SecurityScheme};
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->customizeResetPasswordUrl();
        $this->customizeVerificationUrl();
        $this->configureScramble();
        RateLimiter::for('global', function ($request) {
            return Limit::none();
        });
        RateLimiter::for('api', function ($request) {
            return Limit::none();
        });
    }

    /**
     * Customize the reset password URL.
     */
    private function customizeResetPasswordUrl(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $userType = $notifiable->user_type ?? 'candidate';

            return $this->buildCustomUrl("{$userType}/resetPassword", [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'user_type' => $userType,
            ]);
        });
    }

    /**
     * Build a custom URL for authentication-related actions.
     *
     * @param string $path
     * @param array $params
     * @return string
     */
    private function buildCustomUrl(string $path, array $params): string
    {
        $request = app(Request::class);
        $language = $request->header('Accept-Language', config('app.locale'));
        $baseUrl = config('app.frontend_url');

        $url = "{$baseUrl}/{$path}";
        $query = http_build_query($params);

        return "{$url}?{$query}";
    }

    /**
     * Customize the email verification URL.
     */
    private function customizeVerificationUrl(): void
    {
        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $userType = $notifiable->user_type ?? 'candidate';

            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'user' => $notifiable->getEmailForVerification(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                    'user_type' => $userType,
                ]
            );

            return $this->buildCustomUrl("{$userType}/verifyEmail", [
                'url' => urlencode($verifyUrl),
                'user_type' => $userType,
            ]);
        });
    }

    /**
     * Configure Scramble for API documentation.
     */
    private function configureScramble(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT')
            );
        });
    }
}
