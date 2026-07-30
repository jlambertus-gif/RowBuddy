<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Disputes\Dispute;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Notifications\Mail\DisputeResolvedMail;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(RefreshDatabase::class);

it('sends the DisputeResolved email to both buyer and seller, resolved via the real dispute participant lookup', function () {
    Mail::fake();

    $buyer = User::factory()->create(['language' => 'es']);
    $seller = User::factory()->create();
    $disputes = app(DisputeRepository::class);
    $clock = app(ClockInterface::class);
    $disputeId = (string) Str::uuid();

    $dispute = Dispute::open(
        $disputeId,
        (string) Str::uuid(),
        (string) Str::uuid(),
        (string) $buyer->id,
        (string) $seller->id,
        'not as described',
        $clock,
    );
    $dispute->releaseEvents();
    $disputes->save($dispute);

    $dispute->resolve(
        DisputeResolutionOutcome::RefundToBuyer,
        new Money(11000, new Currency('USD')),
        '999',
        'buyer is correct',
        false,
        $clock,
    );
    $events = $dispute->releaseEvents();
    $disputes->save($dispute);

    foreach ($events as $event) {
        event($event);
    }

    Mail::assertSent(DisputeResolvedMail::class, 2);
    Mail::assertSent(DisputeResolvedMail::class, function (DisputeResolvedMail $mail) use ($buyer) {
        return $mail->hasTo($buyer->email) && $mail->locale === 'es';
    });
    Mail::assertSent(DisputeResolvedMail::class, function (DisputeResolvedMail $mail) use ($seller) {
        return $mail->hasTo($seller->email) && $mail->locale === 'en';
    });
});

it('does not send the DisputeResolved email twice when the event is delivered twice', function () {
    Mail::fake();

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $disputes = app(DisputeRepository::class);
    $clock = app(ClockInterface::class);
    $disputeId = (string) Str::uuid();

    $dispute = Dispute::open($disputeId, (string) Str::uuid(), (string) Str::uuid(), (string) $buyer->id, (string) $seller->id, 'reason', $clock);
    $dispute->releaseEvents();
    $disputes->save($dispute);

    $dispute->resolve(DisputeResolutionOutcome::Cancelled, null, '999', 'notes', false, $clock);
    $events = $dispute->releaseEvents();
    $disputes->save($dispute);

    foreach ($events as $event) {
        event($event);
        event($event);
    }

    Mail::assertSent(DisputeResolvedMail::class, 2);
});
