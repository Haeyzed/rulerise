<?php

namespace App\Providers;

use Carbon\Carbon;
use Dedoc\Scramble\Scramble;
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
    }

    /**
     * Customize the reset password URL.
     */
    private function customizeResetPasswordUrl(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return $this->buildCustomUrl('reset-password', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
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
//        $baseUrl = $request->header('Origin', config('app.frontend_url'));
        $baseUrl = config('app.frontend_url');

//        $url = "{$baseUrl}/{$language}/{$path}";
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
            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'user' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return $this->buildCustomUrl('verify-email', [
                'url' => urlencode($verifyUrl),
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
