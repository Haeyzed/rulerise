<?php

namespace App\Notifications;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * The callback to create the reset password URL.
     */
    public static ?Closure $createUrlCallback = null;

    /**
     * The callback to build the mail message.
     */
    public static ?Closure $toMailCallback = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $resetPasswordUrl = $this->resetPasswordUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $resetPasswordUrl = static::$createUrlCallback
            ? call_user_func(static::$createUrlCallback, $notifiable, $this->token)
            : $this->resetPasswordUrl($notifiable);

        return $this->buildMailMessage($resetPasswordUrl, $notifiable);
    }

    /**
     * Build the reset password mail message.
     */
    protected function buildMailMessage(string $url, mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password')
            ->markdown('emails.reset-password', [
                'url' => $url,
                'name' => $notifiable->name ?? 'User',
                'count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60),
            ]);
    }

    /**
     * Build the custom reset password URL.
     */
    private function resetPasswordUrl(mixed $notifiable): string
    {
        $userType = $notifiable->user_type ?? 'candidate';

        return $this->buildCustomUrl("{$userType}/resetPassword", [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'user_type' => $userType,
        ]);
    }

    /**
     * Build a custom URL for authentication-related actions.
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
     * Set a custom URL creator callback.
     */
    public static function createUrlUsing(Closure $callback): void
    {
        static::$createUrlCallback = $callback;
    }

    /**
     * Set a custom mail message builder callback.
     */
    public static function toMailUsing(Closure $callback): void
    {
        static::$toMailCallback = $callback;
    }
}
