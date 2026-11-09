<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Stripe\Api;
use CraftCms\Stripe\Database\Table;

/**
 * A synced Stripe payment method.
 *
 * @property string $stripeId
 * @property array<string, mixed> $data
 *
 * @since 3.0.0
 */
class PaymentMethod extends BaseModel
{
    use HasUid;

    /**
     * @var string[] Params to expand when fetching payment methods from the Stripe API
     */
    public static array $expandParams = [];

    protected $table = Table::PAYMENTMETHODDATA;

    protected function casts(): array
    {
        return [
            'data' => 'array',
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
        return app(Api::class)->dashboardUrl('customers/'.($this->getData()['customer'] ?? ''));
    }
}
