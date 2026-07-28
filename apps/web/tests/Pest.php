<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Builds a valid Stripe-style `t=...,v1=...` webhook signature header for
 * a given payload/secret — the same HMAC-SHA256 scheme
 * Stripe\WebhookSignature::verifyHeader() checks, so tests can exercise
 * real signature verification without any network call.
 */
function signedStripeWebhookHeader(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}
