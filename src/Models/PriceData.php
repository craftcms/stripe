<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Stripe\Database\Table;

/**
 * A synced Stripe price’s API object.
 *
 * @since 2.0.0
 */
class PriceData extends BaseModel
{
    use HasUid;

    protected $table = Table::PRICEDATA;

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
