<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Enums;

/**
 * Stripe price types.
 *
 * @since 1.0.0
 */
enum PriceType: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
