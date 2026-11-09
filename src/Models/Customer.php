<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Models;

use Carbon\CarbonInterface;
use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Database\Table;

/**
 * A synced Stripe customer.
 *
 * @property string $stripeId
 * @property string $email
 * @property CarbonInterface $stripeCreated
 * @property array<string, mixed> $data
 *
 * @since 2.0.0
 */
class Customer extends BaseModel
{
    use HasUid;

    /**
     * @var string[] Params to expand when fetching customers from the Stripe API
     */
    public static array $expandParams = [];

    protected $table = Table::CUSTOMERDATA;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'stripeCreated' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function getStripeEditUrl(): string
    {
        return app(Api::class)->dashboardUrl("customers/$this->stripeId");
    }
}
