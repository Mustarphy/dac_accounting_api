<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Overrides Laravel's default reset-password notification so the link
 * points at the Next.js frontend's /reset-password page instead of a
 * Laravel web route — this app has no server-rendered password-reset
 * page. $token is the raw Laravel password-broker token (see
 * User::sendPasswordResetNotification()); Laravel still owns generation,
 * hashing and expiry of this token via the built-in password broker.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?'.
            http_build_query(['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject('Reset your DAC Accounting password')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire soon.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
