<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use Illuminate\Contracts\Mail\Mailer;

/**
 * A minimal stand-in for {@see Mailer} — records what was sent, to whom,
 * without ever rendering the mailable's content()/envelope() (which
 * would require a booted Laravel translator this package's own
 * standalone tests never boot, mirroring every other Infrastructure-
 * layer fake in this codebase).
 */
final class FakeMailer implements Mailer
{
    /** @var list<array{to: mixed, mailable: mixed}> */
    public array $sent = [];

    private mixed $pendingTo = null;

    public function to($users): static
    {
        $this->pendingTo = $users;

        return $this;
    }

    public function bcc($users): static
    {
        return $this;
    }

    public function raw($text, $callback)
    {
        return null;
    }

    public function send($view, array $data = [], $callback = null)
    {
        $this->sent[] = ['to' => $this->pendingTo, 'mailable' => $view];

        return null;
    }

    public function sendNow($mailable, array $data = [], $callback = null)
    {
        return null;
    }
}
