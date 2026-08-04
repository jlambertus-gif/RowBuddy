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
 * verification email (Phase 9 stabilization, functional-acceptance
 * finding FA-003) with a self-contained template matching
 * packages/Notifications' own style (translated greeting/body/footer
 * paragraphs, no external branding). Wired via
 * Illuminate\Auth\Notifications\VerifyEmail::toMailUsing() in
 * AppServiceProvider. Implements ShouldQueue per this app's own
 * architecture preset (every Mailable under apps/web/app must queue) —
 * sending happens on Horizon's default queue, not inline in the request.
 */
final class VerifyEmailMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $recipientEmail,
        private readonly string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipientEmail],
            subject: __('auth.verify_email.subject'),
        );
    }

    public function content(): Content
    {
        $body = sprintf(
            '<p>%s</p><p>%s</p><p><a href="%s">%s</a></p><p>%s</p>',
            e(__('auth.verify_email.greeting')),
            e(__('auth.verify_email.body')),
            e($this->verificationUrl),
            e(__('auth.verify_email.action_label')),
            e(__('auth.verify_email.footer')),
        );

        return (new Content)->htmlString($body);
    }
}
