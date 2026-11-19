<?php

declare(strict_types=1);

use CraftCms\Stripe\Settings;

it('requires the API keys', function () {
    $settings = new Settings;

    expect($settings->validate())->toBeFalse()
        ->and($settings->errors()->keys())->toEqualCanonicalizing(['secretKey', 'publishableKey']);
});

it('passes validation with the API keys', function () {
    $settings = new Settings;
    $settings->secretKey = 'sk_test_123';
    $settings->publishableKey = 'pk_test_123';

    expect($settings->validate())->toBeTrue();
});

it('labels the API keys', function () {
    expect((new Settings)->attributeLabels())->toHaveKeys(['secretKey', 'publishableKey']);
});
