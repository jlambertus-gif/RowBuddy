<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Replaces Laravel/Fortify's own default "Laravel <hello@example.com>"
 * password-reset email (Phase 9 stabilization, functional-acceptance
 * finding FA-003) with a self-contained template matching
 * packages/Notifications' own style (translated greeting/body/footer
 * paragraphs, no external branding). Wired via
 * Illuminate\Auth\Notifications\ResetPassword::toMailUsing() in
 * AppServiceProvider. Implements ShouldQueue per this app's own
 * architecture preset (every Mailable under apps/web/app must queue) —
 * sending happens on Horizon's default queue, not inline in the request.
 */
final class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $recipientEmail,
        private readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipientEmail],
            subject: __('auth.reset_password.subject'),
        );
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p><a href="%s">%s</a></p><p>%s</p><p>%s</p>',
            e(__('auth.reset_password.greeting')),
            e(__('auth.reset_password.body')),
            e($this->resetUrl),
            e(__('auth.reset_password.action_label')),
            e(__('auth.reset_password.expires', ['count' => (string) config('auth.passwords.users.expire')])),
            e(__('auth.reset_password.footer')),
        );

        return (new Content)->htmlString($body);
    }
}
