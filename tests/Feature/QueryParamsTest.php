<?php

declare(strict_types=1);

use CraftCms\Stripe\Support\QueryParams;

it('wraps query param values in wildcards', function (mixed $value, mixed $expected) {
    expect(QueryParams::contains($value))->toBe($expected);
})->with([
    'string' => ['usd', '*usd*'],
    'array' => [['usd', 'eur'], ['*usd*', '*eur*']],
    'not operator' => [['not', 'usd'], ['not', '*usd*']],
    'value starting with a colon' => [':empty:', ':empty:'],
    'empty array' => [[], []],
    'null' => [null, null],
]);
