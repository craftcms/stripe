<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Stripe\Database\Table;

/**
 * The webhook endpoint registered with Stripe, and its signing secret.
 *
 * @property string $webhookId
 * @property string $webhookSigningSecret
 *
 * @since 2.0.0
 */
class Webhook extends BaseModel
{
    use HasUid;

    protected $table = Table::WEBHOOKS;
}
