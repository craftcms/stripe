<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Stripe\Database\Table;

/**
 * A synced Stripe product’s API object.
 *
 * @since 2.0.0
 */
class ProductData extends BaseModel
{
    use HasUid;

    protected $table = Table::PRODUCTDATA;

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
