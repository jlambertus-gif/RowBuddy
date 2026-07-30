<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RowBuddy\Notifications\Mail\TransferConfirmedMail;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Transfer;

uses(RefreshDatabase::class);

it('sends the TransferConfirmed email to both buyer and seller, resolved via the real participant lookup', function () {
    Mail::fake();

    $seller = User::factory()->create(['language' => 'es']);
    $buyer = User::factory()->create();
    $transfers = app(TransferRepository::class);
    $clock = app(ClockInterface::class);
    $transferId = (string) Str::uuid();

    $transfer = Transfer::issue(
        $transferId,
        (string) Str::uuid(),
        (string) Str::uuid(),
        (string) $seller->id,
        (string) $buyer->id,
        hash('sha256', 'plaintext-token'),
        $clock->now()->modify('+24 hours'),
        $clock,
    );
    $geo = new GeoPoint(32.7157, -117.1611);
    $transfer->confirmBySeller($geo, $clock);
    $transfer->confirmByBuyer($geo, $clock);
    $events = $transfer->releaseEvents();
    $transfers->save($transfer);

    foreach ($events as $event) {
        event($event);
    }

    Mail::assertSent(TransferConfirmedMail::class, 2);
    Mail::assertSent(TransferConfirmedMail::class, function (TransferConfirmedMail $mail) use ($seller) {
        return $mail->hasTo($seller->email) && $mail->locale === 'es';
    });
    Mail::assertSent(TransferConfirmedMail::class, function (TransferConfirmedMail $mail) use ($buyer) {
        return $mail->hasTo($buyer->email) && $mail->locale === 'en';
    });
});

it('does not send the TransferConfirmed email twice when the event is delivered twice', function () {
    Mail::fake();

    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transfers = app(TransferRepository::class);
    $clock = app(ClockInterface::class);
    $transferId = (string) Str::uuid();

    $transfer = Transfer::issue(
        $transferId,
        (string) Str::uuid(),
        (string) Str::uuid(),
        (string) $seller->id,
        (string) $buyer->id,
        hash('sha256', 'plaintext-token'),
        $clock->now()->modify('+24 hours'),
        $clock,
    );
    $geo = new GeoPoint(32.7157, -117.1611);
    $transfer->confirmBySeller($geo, $clock);
    $transfer->confirmByBuyer($geo, $clock);
    $events = $transfer->releaseEvents();
    $transfers->save($transfer);

    foreach ($events as $event) {
        event($event);
        event($event);
    }

    Mail::assertSent(TransferConfirmedMail::class, 2);
});
