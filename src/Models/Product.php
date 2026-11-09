<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Stripe\Database\Table;

/**
 * A row in the Product element’s table. Its ID is the element’s ID.
 *
 * @property string $stripeId
 * @property \DateTimeInterface|string|null $dateCreated
 * @property \DateTimeInterface|string|null $dateUpdated
 *
 * @since 3.0.0
 */
class Product extends BaseModel
{
    use HasUid;

    public $incrementing = false;

    protected $table = Table::PRODUCTS;
}
