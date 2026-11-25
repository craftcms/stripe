<?php

declare(strict_types=1);

use CraftCms\Stripe\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Signs a Stripe event payload the way Stripe documents it, independently of the SDK.
 *
 * @param  array<string, mixed>  $object
 * @return array{0: string, 1: string} The payload and its `Stripe-Signature` header
 */
function signedStripeEvent(string $type, array $object, string $secret = TestCase::WEBHOOK_SECRET): array
{
    $payload = json_encode([
        'id' => 'evt_test_1',
        'object' => 'event',
        'api_version' => '2026-01-28.clover',
        'type' => $type,
        'data' => ['object' => $object],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();

    return [$payload, "t=$timestamp,v1=".hash_hmac('sha256', "$timestamp.$payload", $secret)];
}
