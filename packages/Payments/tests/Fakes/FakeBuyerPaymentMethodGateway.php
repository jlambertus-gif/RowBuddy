<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\BuyerPaymentMethodGateway;
use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\ValueObjects\ConfirmedPaymentMethod;
use RowBuddy\Payments\ValueObjects\SetupIntentDraft;

final class FakeBuyerPaymentMethodGateway implements BuyerPaymentMethodGateway
{
    /** @var list<string> */
    public array $createCustomerCalls = [];

    /** @var list<string> */
    public array $createSetupIntentCalls = [];

    public string $nextStripeCustomerId = 'cus_fake';

    public string $nextSetupIntentId = 'seti_fake';

    public string $nextClientSecret = 'seti_fake_secret_abc';

    public bool $nextSetupIntentSucceeded = true;

    public ?string $nextSetupIntentBuyerId = null;

    public string $nextStripePaymentMethodId = 'pm_fake';

    public function createCustomer(string $buyerId): string
    {
        $this->createCustomerCalls[] = $buyerId;
        $this->nextSetupIntentBuyerId ??= $buyerId;

        return $this->nextStripeCustomerId;
    }

    public function createSetupIntent(string $stripeCustomerId): SetupIntentDraft
    {
        $this->createSetupIntentCalls[] = $stripeCustomerId;

        return new SetupIntentDraft($stripeCustomerId, $this->nextSetupIntentId, $this->nextClientSecret);
    }

    public function retrieveConfirmedPaymentMethod(string $setupIntentId, string $buyerId): ConfirmedPaymentMethod
    {
        if (! $this->nextSetupIntentSucceeded) {
            throw SetupIntentNotConfirmed::forSetupIntentId($setupIntentId);
        }

        if ($this->nextSetupIntentBuyerId !== null && $this->nextSetupIntentBuyerId !== $buyerId) {
            throw SetupIntentBuyerMismatch::forSetupIntentId($setupIntentId);
        }

        return new ConfirmedPaymentMethod($this->nextStripeCustomerId, $this->nextStripePaymentMethodId);
    }
}
