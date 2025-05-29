<?php

namespace App\Notifications;

use Closure;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public string $token;



    /**
     * The callback that should be used to create the reset password URL.
     *
     * @var \Closure|null
     */
    public static $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $resetPasswordUrl = $this->resetPasswordUrl($notifiable);
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

//        if (static::$createUrlCallback) {
//            $url = call_user_func(static::$createUrlCallback, $notifiable, $this->token);
//        } else {
//            $url = url(route('password.reset', [
//                'token' => $this->token,
//                'email' => $notifiable->getEmailForPasswordReset(),
//            ], false));
//        }

        return $this->buildMailMessage($resetPasswordUrl);
    }

    /**
     * Get the reset password notification mail message for the given URL.
     *
     * @param string $url
     * @return MailMessage
     */
    protected function buildMailMessage(string $url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password')
            ->markdown('emails.reset-password', [
                'url' => $url,
                'name' => $notifiable->name ?? 'User',
                'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }

    /**
     * Customize the reset password URL.
     */
    private function resetPasswordUrl(mixed $notifiable): string
    {
//        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $userType = $notifiable->user_type ?? 'candidate';

            return $this->buildCustomUrl("{$userType}/resetPassword", [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'user_type' => $userType,
            ]);
//        });
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
     * Set a callback that should be used when creating the reset password button URL.
     *
     * @param Closure $callback
     * @return void
     */
    public static function createUrlUsing(Closure $callback): void
    {
        static::$createUrlCallback = $callback;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param Closure $callback
     * @return void
     */
    public static function toMailUsing(Closure $callback): void
    {
        static::$toMailCallback = $callback;
    }
}
